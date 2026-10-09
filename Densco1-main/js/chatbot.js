/* Densco Chatbot
   - Local FAQ responses
   - Live product stock lookup
   - Gemini AI responses through /api/chatbot.php
   - Escalation to staff when requested or AI is unavailable
*/

(function () {
  "use strict";

  var D = window.DHP;
  var KEY = "denscoInquiries";
  var SESS = "denscoActiveInquiry";
  var POLL = 4000;

  var who = D.user() || "Guest visitor";
  var awaitingProductName = false;
  var active = null;
  var seen = 0;
  var busy = false;

  // Business FAQ responses
  var FAQ = [
    [
      /\b(hello|hi|hey)\b/,
      "Hello! I can check real-time stock, explain delivery and payment, answer product questions, or connect you with our team."
    ],
    [
      /deliver|shipping|ship|pickup|pick up/,
      "Metro Manila delivery takes 1 to 2 days. You can also choose free store pickup at our Quezon City warehouse at checkout."
    ],
    [
      /payment|pay|gcash|bank|proof|cash/,
      "We accept GCash / QR PH, bank transfer and cash. GCash and bank transfer orders need a proof of payment uploaded at checkout."
    ],
    [
      /order|status|track/,
      "You can follow every order from Pending to Completed under My Account."
    ],
    [
      /hours|open|location|address|where/,
      "We are open " +
        D.biz().hours.toLowerCase() +
        " at " +
        D.biz().address +
        "."
    ],
    [
      /return|refund|warranty/,
      "Most items carry a 1-year manufacturer warranty. For returns or replacements, message us on Viber or Messenger."
    ]
  ];

  // Find products using actual product names from the website
  function findProduct(message) {
    var products = D.products();
    var text = message.toLowerCase();

    var exactMatch = products.filter(function (product) {
      return text.indexOf(product.name.toLowerCase()) !== -1;
    })[0];

    if (exactMatch) return exactMatch;

    var STOP = {
      of: 1,
      box: 1,
      pack: 1,
      the: 1,
      and: 1,
      for: 1,
      with: 1
    };

    for (var i = 0; i < products.length; i++) {
      var words = products[i].name
        .toLowerCase()
        .split(/[\s()\u2014-]+/);

      for (var j = 0; j < words.length; j++) {
        var word = words[j];

        if (
          word.length > 3 &&
          !STOP[word] &&
          text.indexOf(word) !== -1
        ) {
          return products[i];
        }
      }

      var compactName = products[i].name
        .toLowerCase()
        .replace(/[^a-z0-9]/g, "");

      if (compactName && text.indexOf(compactName) !== -1) {
        return products[i];
      }
    }

    return null;
  }

  // Read existing customer inquiries
  function read() {
    return D.get(KEY, []);
  }

  function find(id) {
    return read().filter(function (item) {
      return item.id === id;
    })[0];
  }

  // Local answers for questions the website can answer accurately
  function localReply(message) {
    var text = message.toLowerCase();

    if (awaitingProductName) {
      awaitingProductName = false;

      var product = findProduct(text);

      if (product) {
        return {
          text:
            product.name +
            ": " +
            D.stateLabel(product).toLowerCase() +
            "."
        };
      }
    }

    // Request to speak to a real person
    if (/staff|human|agent|person|representative|talk to/.test(text)) {
      return {
        text:
          "I'll pass this to a Densco team member. They will answer right here.",
        esc: true
      };
    }

    // Stock lookup
    if (/stock|available|availability|inventory|how many/.test(text)) {
      var match = findProduct(text);

      if (match) {
        return {
          text:
            match.name +
            ": " +
            D.stateLabel(match).toLowerCase() +
            "."
        };
      }

      awaitingProductName = true;

      return {
        text:
          "Which product should I check? Type the product name, for example: hospital bed, oxygen cart, stretcher, IV stand or medicine cabinet."
      };
    }

    // Fixed FAQ responses
    for (var k = 0; k < FAQ.length; k++) {
      if (FAQ[k][0].test(text)) {
        return {
          text: FAQ[k][1]
        };
      }
    }

    // Send other questions to Gemini
    return null;
  }

  // Call the PHP API that securely communicates with Gemini
  function askAI(message) {
    return fetch("/api/chatbot.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        message: message
      })
    })
      .then(function (response) {
        return response.text().then(function (body) {
          var data;

          try {
            data = JSON.parse(body);
          } catch (error) {
            console.error("Chatbot returned non-JSON:", body);

            throw new Error(
              "The chatbot API returned an invalid response. HTTP status: " +
                response.status
            );
          }

          if (!response.ok) {
            throw new Error(
              data.error ||
                "Chatbot request failed. HTTP status: " +
                  response.status
            );
          }

          return data;
        });
      })
      .then(function (data) {
        if (data && data.success && data.message) {
          return cleanAI(data.message);
        }

        throw new Error(
          (data && data.error) || "No AI response received."
        );
      });
  }

  // Remove common Markdown formatting
  function cleanAI(message) {
    return String(message)
      .replace(/\*\*(.+?)\*\*/g, "$1")
      .replace(/^\s*[*-]\s+/gm, "\u2022 ")
      .replace(/[*_`#]/g, "")
      .trim();
  }

  // Add chatbot interface
  document.body.insertAdjacentHTML(
    "beforeend",
    '<div class="cb" id="cb">' +
      '<div class="cb-win">' +
        '<div class="cb-head">' +
          '<div>' +
            '<b>Densco Assistant</b>' +
            '<small>Answers instantly, or hands you to our team</small>' +
          '</div>' +
          '<button class="cb-x" id="cbX" aria-label="Close chat">&times;</button>' +
        '</div>' +
        '<div class="cb-msgs" id="cbMsgs">' +
          '<div class="cb-m bot">Hi! Ask about product stock, delivery, payment or your orders.</div>' +
        '</div>' +
        '<div class="cb-chips" id="cbChips">' +
          '<button>Check stock</button>' +
          '<button>Delivery and pickup</button>' +
          '<button>Payment options</button>' +
          '<button>Talk to staff</button>' +
        '</div>' +
        '<form class="cb-form" id="cbForm">' +
          '<input id="cbIn" placeholder="Type a message" autocomplete="off">' +
          '<button type="submit" class="btn btn-red btn-sm">Send</button>' +
        '</form>' +
      '</div>' +
      '<button class="cb-fab" id="cbFab" aria-label="Open chat">' +
        '<svg width="24" height="24" viewBox="0 0 24 24" fill="none">' +
          '<path d="M4 4H20V16H7L4 19V4Z" stroke="#fff" stroke-width="1.7" stroke-linejoin="round"/>' +
        '</svg>' +
      '</button>' +
    '</div>'
  );

  var cb = document.getElementById("cb");
  var msgs = document.getElementById("cbMsgs");
  var input = document.getElementById("cbIn");

  // Display a message in the chat
  function add(message, sender) {
    var element = document.createElement("div");

    element.className = "cb-m " + sender;
    element.textContent = message;

    msgs.appendChild(element);
    msgs.scrollTop = msgs.scrollHeight;

    return element;
  }

  // Restore an existing staff inquiry, if one exists
  try {
    var sessionId = sessionStorage.getItem(SESS);
    var existing = sessionId && find(sessionId);

    if (existing) {
      active = sessionId;

      existing.messages.forEach(function (message) {
        add(
          message.text,
          message.sender === "staff" ? "staff" : "user"
        );
      });

      seen = existing.messages.length;
    }
  } catch (error) {
    console.warn("Could not restore chat session:", error);
  }

  // Create a staff inquiry and save it using the existing website storage
  function escalate(message) {
    var all = read();

    var inquiry = {
      id: "INQ-" + Date.now(),
      customer: who,
      question: message,
      status: "open",
      messages: [
        {
          sender: "customer",
          text: message,
          time: new Date().toISOString()
        }
      ]
    };

    all.unshift(inquiry);
    D.put(KEY, all);

    active = inquiry.id;
    seen = 1;

    try {
      sessionStorage.setItem(SESS, inquiry.id);
    } catch (error) {
      console.warn("Could not save chat session:", error);
    }
  }

  // Send a customer message
  function send(message) {
    if (busy) return;

    add(message, "user");

    // If already connected to staff, save the customer's message
    if (active) {
      var all = read();

      all.forEach(function (inquiry) {
        if (inquiry.id === active) {
          inquiry.messages.push({
            sender: "customer",
            text: message,
            time: new Date().toISOString()
          });

          seen = inquiry.messages.length;
        }
      });

      D.put(KEY, all);
      return;
    }

    // First try local FAQ and stock responses
    var local = localReply(message);

    if (local) {
      setTimeout(function () {
        add(local.text, "bot");

        if (local.esc) {
          escalate(message);
        }
      }, 300);

      return;
    }

    // Otherwise, request a Gemini AI response
    busy = true;

    var typing = add("Typing...", "bot");

    askAI(message)
      .then(function (answer) {
        typing.textContent = answer;
      })
      .catch(function (error) {
        console.error("Chatbot AI error:", error);

        typing.textContent =
          "I can't answer that right now, so I'm connecting you with a Densco team member. They will reply right here.";

        escalate(message);
      })
      .then(function () {
        busy = false;
        msgs.scrollTop = msgs.scrollHeight;
      });
  }

  // Open and close the chatbot
  document.getElementById("cbFab").onclick = function () {
    cb.classList.add("open");
    input.focus();
  };

  document.getElementById("cbX").onclick = function () {
    cb.classList.remove("open");
  };

  // Submit typed messages
  document.getElementById("cbForm").onsubmit = function (event) {
    event.preventDefault();

    var message = input.value.trim();

    if (message) {
      input.value = "";
      send(message);
    }
  };

  // Handle suggested question buttons
  document.getElementById("cbChips").onclick = function (event) {
    if (event.target.tagName === "BUTTON") {
      send(event.target.textContent);
    }
  };

  // Check for staff replies to the active inquiry
  setInterval(function () {
    if (!active) return;

    var inquiry = find(active);

    if (!inquiry) return;

    for (var i = seen; i < inquiry.messages.length; i++) {
      if (inquiry.messages[i].sender === "staff") {
        add(inquiry.messages[i].text, "staff");
      }
    }

    seen = inquiry.messages.length;
  }, POLL);
})();
