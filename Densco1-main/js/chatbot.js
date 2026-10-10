/* Densco Chatbot
   - Local FAQ responses
   - Live product stock lookup
   - Gemini AI responses through api/chatbot.php
   - Escalation to staff when requested or AI is unavailable
*/

(function () {
  "use strict";

  var D = window.DHP;
  var KEY = "denscoInquiries";
  var POLL = 4000;

  // Path to the PHP file. It starts with "/" so it works from ANY page
  // (home, shop, My Account, admin). A path like "api/chatbot.php" breaks as
  // soon as the page is in a different folder, for example after logging in.
  // On a live website at the domain root, change this to "/api/chatbot.php".
  var API_URL = "/densco/Densco1-main/api/chatbot.php";

  var GREETING =
    "Good day! I am the Densco Assistant. How may I help you with product availability, delivery, payment or your orders?";

  // A plain-text identity for whoever is using the site right now,
  // so a chat can be tied to one account (or to the guest).
  function currentUser() {
    return D.user() || GUEST;
  }

  function userKey(user) {
    if (user && typeof user === "object") {
      return String(user.email || user.id || user.username || user.name || JSON.stringify(user));
    }
    return String(user);
  }

  var GUEST = "Guest visitor";
  var LOGIN_MSG =
    "Chatting with our team is available to logged-in customers. Please log in to your Densco account to continue, or message us on Viber or Messenger. In the meantime, I will gladly answer your questions about our products, delivery and payment.";

  var who = currentUser();
  var whoKey = userKey(who);

  // Only logged-in customers can reach the staff (admin) chat
  function isLoggedIn() {
    return whoKey !== GUEST;
  }
  var awaitingProductName = false;
  var active = null;
  var seen = 0;
  var busy = false;

  // Remembers the last few messages so the AI can follow the conversation
  var history = [];

  function remember(role, text) {
    history.push({ role: role, text: text });
    if (history.length > 12) history.shift();
  }

  // Gives the AI your real business info, live stock levels and policies
  function buildContext() {
    try {
      var biz = D.biz();
      var lines = D.products().map(function (product) {
        var line = "- " + product.name + ": " + D.stateLabel(product);

        // Optional fields: only added if your products have them
        if (product.price !== undefined && product.price !== null && product.price !== "") {
          line += ", price: PHP " + product.price;
        }
        if (product.description) {
          line += ". " + product.description;
        }
        return line;
      });

      return (
        "Business hours: " + biz.hours + ". " +
        "Address: " + biz.address + ".\n" +
        "Delivery: Metro Manila delivery takes 1 to 2 days. Free store pickup is available at the Quezon City warehouse.\n" +
        "Payment: GCash / QR PH, bank transfer and cash. GCash and bank transfer orders need proof of payment uploaded at checkout.\n" +
        "Warranty: Most items carry a 1-year manufacturer warranty. For returns or replacements, customers message Densco on Viber or Messenger.\n" +
        "Customer account: " +
        (isLoggedIn()
          ? "logged in. The customer can type 'talk to staff' to chat with the Densco team."
          : "guest, not logged in. Staff chat is only available after logging in, so ask the customer to log in first or to contact Densco on Viber or Messenger.") +
        "\n" +
        "Products and live stock:\n" + lines.join("\n")
      );
    } catch (error) {
      return "";
    }
  }

  // Business FAQ responses
  var FAQ = [
    [
      /\b(hello|hi|hey)\b/,
      "Good day! Welcome to Densco. I can help you check product availability, explain our delivery and payment options, or connect you with our team. How may I assist you today?"
    ],
    [
      /deliver|shipping|ship|pickup|pick up/,
      "Delivery within Metro Manila takes 1 to 2 days. Alternatively, you may choose free store pickup at our Quezon City warehouse during checkout."
    ],
    [
      /payment|pay|gcash|bank|proof|cash/,
      "We accept GCash / QR PH, bank transfer and cash. For GCash and bank transfer payments, please upload your proof of payment during checkout."
    ],
    [
      /order|status|track/,
      "You may track your order status, from Pending to Completed, under My Account."
    ],
    [
      /hours|open|location|address|where/,
      "Our business hours are " +
        D.biz().hours.toLowerCase() +
        ". We are located at " +
        D.biz().address +
        "."
    ],
    [
      /return|refund|warranty/,
      "Most of our items come with a 1-year manufacturer warranty. For returns or replacements, please message us on Viber or Messenger and our team will gladly assist you."
    ]
  ];

  // Words that never identify a product
  var NOISE = {
    how:1, much:1, many:1, the:1, you:1, your:1, our:1, have:1, has:1, any:1,
    got:1, sell:1, selling:1, price:1, prices:1, cost:1, stock:1, stocks:1,
    available:1, availability:1, inventory:1, please:1, can:1, could:1,
    would:1, want:1, need:1, buy:1, for:1, with:1, and:1, what:1, whats:1,
    there:1, this:1, that:1, some:1, does:1, about:1, check:1, tell:1,
    list:1, are:1, magkano:1, meron:1, ito:1, box:1, pack:1, units:1, unit:1
  };

  // lower-case, strip symbols, drop a trailing "s" so "carts" matches "cart"
  function words(text) {
    return text
      .toLowerCase()
      .split(/[^a-z0-9]+/)
      .filter(function (w) { return w.length > 2; })
      .map(function (w) { return w.replace(/s$/, ""); });
  }

  // Find the product the customer is really asking about.
  // A product only matches if most of the customer's key words are in its name,
  // so "oxygen cart" will NOT wrongly match "Utility Cart".
  function findProduct(message) {
    var products = D.products();
    var text = message.toLowerCase();

    // 1. The full product name was typed
    var exactMatch = products.filter(function (product) {
      return text.indexOf(product.name.toLowerCase()) !== -1;
    })[0];

    if (exactMatch) return exactMatch;

    // 2. Word scoring
    var query = words(text).filter(function (w) {
      return !NOISE[w] && !NOISE[w + "s"];
    });

    if (!query.length) return null;

    var best = null;
    var bestHits = 0;
    var bestTight = 0;

    products.forEach(function (product) {
      var nameWords = words(product.name).filter(function (w) {
        return !NOISE[w] && !NOISE[w + "s"];
      });

      var hits = query.filter(function (q) {
        return nameWords.indexOf(q) !== -1;
      }).length;

      if (!hits || hits / query.length < 0.6) return;

      var tight = hits / (nameWords.length || 1);

      if (hits > bestHits || (hits === bestHits && tight > bestTight)) {
        best = product;
        bestHits = hits;
        bestTight = tight;
      }
    });

    return best;
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

  // The built-in answers are English only. If the customer writes in another
  // language (Tagalog, Cebuano, Spanish, Chinese, Arabic, ...), skip them and
  // let Gemini answer in the customer's own language.
  var ENGLISH_HINT =
    /\b(the|you|your|yours|have|has|how|much|many|what|when|where|which|who|is|are|do|does|can|could|would|please|thanks|thank|stock|price|cost|available|delivery|deliver|shipping|payment|pay|order|orders|hours|open|address|location|warranty|return|refund|staff|human|agent|person|hello|hi|hey|check|product|products|pickup|gcash|bank|cash|back|bot)\b/i;

  function looksEnglish(text) {
    var asciiOnly = !/[^\u0000-\u007F]/.test(text);
    return asciiOnly && ENGLISH_HINT.test(text);
  }

  // Professional stock reply used for every product lookup
  function stockReply(product) {
    return (
      "Thank you for your inquiry. Our " +
      product.name +
      " is currently: " +
      D.stateLabel(product).toLowerCase() +
      ". Would you like to know about our delivery or payment options?"
    );
  }

  // Local answers for questions the website can answer accurately
  function localReply(message) {
    var text = message.toLowerCase();

    if (awaitingProductName) {
      awaitingProductName = false;

      var product = findProduct(text);

      if (product) {
        return { text: stockReply(product) };
      }
    }

    // Request to speak to a real person
    if (/staff|human|agent|person|representative|talk to/.test(text)) {
      return {
        text:
          "Certainly. I am connecting you with a Densco team member, who will reply to you here shortly.",
        esc: true
      };
    }

    // Stock lookup
    if (/stock|available|availability|inventory|how many/.test(text)) {
      var match = findProduct(text);

      if (match) {
        return { text: stockReply(match) };
      }

      awaitingProductName = true;

      return {
        text:
          "Certainly. Which product would you like me to check? Please type the product name, for example: hospital bed, oxygen cart, stretcher, IV stand or medicine cabinet."
      };
    }

    // If the message mentions a known product, answer from live data
    var mentioned = findProduct(text);

    if (mentioned) {
      // Price questions: only answer if the product really has a price
      if (/price|cost|how much|magkano/.test(text)) {
        if (
          mentioned.price !== undefined &&
          mentioned.price !== null &&
          mentioned.price !== ""
        ) {
          return {
            text:
              "The " +
              mentioned.name +
              " is priced at PHP " +
              Number(mentioned.price).toLocaleString("en-PH") +
              ". Current availability: " +
              D.stateLabel(mentioned).toLowerCase() +
              ". Would you like to know about our delivery or payment options?"
          };
        }
        return null; // let the AI answer from the business data
      }

      // Availability questions
      if (/have|got|sell|buy/.test(text)) {
        return { text: stockReply(mentioned) };
      }
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
    return fetch(API_URL, {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        message: message,
        history: history,
        context: buildContext()
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
          '<div class="cb-m bot">' + GREETING + '</div>' +
        '</div>' +
        '<div class="cb-chips" id="cbChips">' +
          '<button>Check stock</button>' +
          '<button>Delivery and pickup</button>' +
          '<button>Payment options</button>' +
          '<button>Talk to staff</button>' +
          '<button>Back to bot</button>' +
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

  // ---- Chat storage -------------------------------------------------
  // Logged-in customers: the chat is saved under their account and is still
  // there after a refresh or the next time they log in.
  // Guests: nothing is saved. A refresh starts a brand new chat.
  function storageKey() {
    return "denscoChat:" + whoKey;
  }

  // Save everything currently shown in the chat window
  function save() {
    if (!isLoggedIn()) return;

    try {
      var list = [];

      Array.prototype.forEach.call(msgs.children, function (el) {
        if (el.textContent === "Typing...") return;

        list.push({
          sender: el.classList.contains("user")
            ? "user"
            : el.classList.contains("staff")
            ? "staff"
            : "bot",
          text: el.textContent
        });
      });

      localStorage.setItem(
        storageKey(),
        JSON.stringify({
          messages: list.slice(-100),
          history: history,
          active: active,
          seen: seen
        })
      );
    } catch (error) {
      console.warn("Could not save chat:", error);
    }
  }

  // Display a message in the chat
  function add(message, sender, skipSave) {
    var element = document.createElement("div");

    element.className = "cb-m " + sender;
    element.textContent = message;

    msgs.appendChild(element);
    msgs.scrollTop = msgs.scrollHeight;

    if (!skipSave) save();

    return element;
  }

  // Load the saved chat of whoever is using the site right now
  function loadChat() {
    active = null;
    seen = 0;
    busy = false;
    awaitingProductName = false;
    history = [];
    msgs.innerHTML = "";

    var saved = null;

    try {
      // Remove any guest chat saved by an earlier version of this script
      sessionStorage.removeItem("denscoChatGuest");

      if (isLoggedIn()) {
        saved = JSON.parse(localStorage.getItem(storageKey()) || "null");
      }
    } catch (error) {
      saved = null;
    }

    if (saved && saved.messages && saved.messages.length) {
      saved.messages.forEach(function (m) {
        add(m.text, m.sender, true);
      });

      history = saved.history || [];

      // Reconnect to the staff conversation (logged-in customers only)
      if (isLoggedIn() && saved.active && find(saved.active)) {
        active = saved.active;
        seen = saved.seen || 0;
      }
    } else {
      add(GREETING, "bot", true);
    }
  }

  loadChat();

  // Create a staff inquiry and save it using the existing website storage
  function escalate(message) {
    if (!isLoggedIn()) return;

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
    save();
  }

  // Send a customer message
  function send(message) {
    if (busy) return;

    add(message, "user");

    // Let the customer leave staff mode and return to the assistant
    if (/^(back to bot|bot|assistant)$/i.test(message.trim())) {
      active = null;
      add("Welcome back. This is the Densco Assistant. How may I help you?", "bot");
      return;
    }

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
      add("Your message has been sent. A Densco team member will reply here shortly. You may type \"back to bot\" to return to the assistant.", "bot");
      return;
    }

    // Built-in answers only for English; other languages go to Gemini
    var local = looksEnglish(message) ? localReply(message) : null;

    if (local) {
      var wantsStaff = local.esc;

      // Guests cannot open a staff chat: ask them to log in instead
      if (wantsStaff && !isLoggedIn()) {
        local.text = LOGIN_MSG;
        wantsStaff = false;
      }

      remember("user", message);
      remember("model", local.text);

      setTimeout(function () {
        add(local.text, "bot");

        if (wantsStaff) {
          escalate(message);
        }
      }, 300);

      return;
    }

    // Otherwise, request a Gemini AI response
    busy = true;

    var typing = add("Typing...", "bot", true);

    askAI(message)
      .then(function (answer) {
        typing.textContent = answer;

        remember("user", message);
        remember("model", answer);
      })
      .catch(function (error) {
        console.error("Chatbot AI error:", error);

        if (isLoggedIn()) {
          typing.textContent =
            "I apologize, I am unable to answer that at the moment. I am connecting you with a Densco team member, who will reply to you here shortly.";
          escalate(message);
        } else {
          typing.textContent =
            "I apologize, I am unable to answer that at the moment. Please log in to chat with our team, or message us on Viber or Messenger.";
        }
      })
      .then(function () {
        busy = false;
        msgs.scrollTop = msgs.scrollHeight;
        save();
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

  // Check for staff replies, and for login or logout without a page reload
  setInterval(function () {
    var nowUser = currentUser();

    if (userKey(nowUser) !== whoKey) {
      who = nowUser;
      whoKey = userKey(nowUser);
      loadChat();
      return;
    }

    if (!active) return;

    var inquiry = find(active);

    if (!inquiry) return;

    var changed = false;

    for (var i = seen; i < inquiry.messages.length; i++) {
      if (inquiry.messages[i].sender === "staff") {
        add(inquiry.messages[i].text, "staff", true);
        changed = true;
      }
    }

    seen = inquiry.messages.length;

    if (changed) save();
  }, POLL);
})();