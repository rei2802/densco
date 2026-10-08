/* Densco — chatbot: live stock lookup + FAQ (local), Gemini AI (chatbot.php) for everything else,
   escalation to the staff inquiry queue when a human is requested or the AI is unavailable */
(function () {
  "use strict";
  var D = window.DHP,
    KEY = "denscoInquiries",
    SESS = "denscoActiveInquiry",
    POLL = 4000;
  var who = D.user() || "Guest visitor";

  // Quick, accurate answers that depend on business settings (hours, address)
  var FAQ = [
    [
      /\b(hello|hi|hey)\b/,
      "Hello! I can check real-time stock, explain delivery and payment, answer product questions, or connect you with our team.",
    ],
    [
      /deliver|shipping|ship|pickup|pick up/,
      "Metro Manila delivery takes 1 to 2 days. You can also choose free store pickup at our Quezon City warehouse at checkout.",
    ],
    [
      /payment|pay|gcash|bank|proof|cash/,
      "We accept GCash / QR PH, bank transfer and cash. GCash and bank transfer orders need a proof of payment uploaded at checkout.",
    ],
    [
      /order|status|track/,
      "You can follow every order from Pending to Completed under My Account.",
    ],
    [
      /hours|open|location|address|where/,
      "We are open " +
        D.biz().hours.toLowerCase() +
        " at " +
        D.biz().address +
        ".",
    ],
    [
      /return|refund|warranty/,
      "Most items carry a 1-year manufacturer warranty. For returns or replacements, message us on Viber or Messenger.",
    ],
  ];

  // Matches free text against real product names from the database
  function findProduct(l) {
    var P = D.products();
    var match = P.filter(function (p) {
      return l.indexOf(p.name.toLowerCase()) > -1;
    })[0];
    if (match) return match;
    var STOP = { of: 1, box: 1, pack: 1, the: 1, and: 1, for: 1, with: 1 };
    for (var i = 0; i < P.length; i++) {
      var words = P[i].name.toLowerCase().split(/[\s()\u2014-]+/);
      for (var j = 0; j < words.length; j++) {
        var w = words[j];
        if (w.length > 3 && !STOP[w] && l.indexOf(w) > -1) return P[i];
      }
      if (l.indexOf(P[i].name.toLowerCase().replace(/[^a-z0-9]/g, "")) > -1)
        return P[i];
    }
    return null;
  }

  function read() {
    return D.get(KEY, []);
  }
  function find(id) {
    return read().filter(function (i) {
      return i.id === id;
    })[0];
  }

  var awaitingProductName = false;

  /* Local rules only. Returns {text, esc?} or null when the message should go to the AI. */
  function localReply(t) {
    var l = t.toLowerCase();

    if (awaitingProductName) {
      awaitingProductName = false;
      var direct = findProduct(l);
      if (direct)
        return {
          text: direct.name + ": " + D.stateLabel(direct).toLowerCase() + ".",
        };
    }

    if (/staff|human|agent|person|representative|talk to/.test(l))
      return {
        text: "I'll pass this to a Densco team member. They will answer right here.",
        esc: true,
      };

    if (/stock|available|availability|inventory|how many/.test(l)) {
      var match = findProduct(l);
      if (match)
        return {
          text: match.name + ": " + D.stateLabel(match).toLowerCase() + ".",
        };
      awaitingProductName = true;
      return {
        text: "Which product should I check? Type the product name, for example: hospital bed, oxygen cart, stretcher, IV stand or medicine cabinet.",
      };
    }

    // Fixed business info: answer locally so hours, address, etc. are always exact
    for (var k = 0; k < FAQ.length; k++)
      if (FAQ[k][0].test(l)) return { text: FAQ[k][1] };

    return null; // let Gemini handle it
  }

  /* Calls chatbot.php (Gemini). Resolves with the reply text, or rejects on any failure. */
  function askAI(text) {
    return fetch("chatbot.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ message: text }),
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (d) {
        if (d && d.success && d.message) return cleanAI(d.message);
        throw new Error((d && d.error) || "No AI response");
      });
  }

  // The widget shows plain text, so strip common markdown from Gemini's answer
  function cleanAI(s) {
    return String(s)
      .replace(/\*\*(.+?)\*\*/g, "$1")
      .replace(/^\s*[*-]\s+/gm, "\u2022 ")
      .replace(/[*_`#]/g, "")
      .trim();
  }

  document.body.insertAdjacentHTML(
    "beforeend",
    '<div class="cb" id="cb"><div class="cb-win"><div class="cb-head"><div><b>Densco Assistant</b><small>Answers instantly, or hands you to our team</small></div><button class="cb-x" id="cbX" aria-label="Close chat">&times;</button></div>' +
      '<div class="cb-msgs" id="cbMsgs"><div class="cb-m bot">Hi! Ask about product stock, delivery, payment or your orders.</div></div>' +
      '<div class="cb-chips" id="cbChips"><button>Check stock</button><button>Delivery and pickup</button><button>Payment options</button><button>Talk to staff</button></div>' +
      '<form class="cb-form" id="cbForm"><input id="cbIn" placeholder="Type a message" autocomplete="off"><button class="btn btn-red btn-sm">Send</button></form></div>' +
      '<button class="cb-fab" id="cbFab" aria-label="Open chat"><svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M4 4H20V16H7L4 19V4Z" stroke="#fff" stroke-width="1.7" stroke-linejoin="round"/></svg></button></div>',
  );

  var cb = document.getElementById("cb"),
    msgs = document.getElementById("cbMsgs"),
    input = document.getElementById("cbIn");
  var active = null,
    seen = 0,
    busy = false;

  function add(t, s) {
    var m = document.createElement("div");
    m.className = "cb-m " + s;
    m.textContent = t;
    msgs.appendChild(m);
    msgs.scrollTop = msgs.scrollHeight;
    return m;
  }

  try {
    var sid = sessionStorage.getItem(SESS),
      ex = sid && find(sid);
    if (ex) {
      active = sid;
      ex.messages.forEach(function (m) {
        add(m.text, m.sender === "staff" ? "staff" : "user");
      });
      seen = ex.messages.length;
    }
  } catch (e) {}

  // Creates a new staff inquiry from the customer's message
  function escalate(text) {
    var all = read(),
      q = {
        id: "INQ-" + Date.now(),
        customer: who,
        question: text,
        status: "open",
        messages: [
          { sender: "customer", text: text, time: new Date().toISOString() },
        ],
      };
    all.unshift(q);
    D.put(KEY, all);
    active = q.id;
    seen = 1;
    try {
      sessionStorage.setItem(SESS, q.id);
    } catch (e) {}
  }

  function send(text) {
    if (busy) return;
    add(text, "user");

    // Already talking to staff: just append to that thread
    if (active) {
      var all = read();
      all.forEach(function (i) {
        if (i.id === active) {
          i.messages.push({
            sender: "customer",
            text: text,
            time: new Date().toISOString(),
          });
          seen = i.messages.length;
        }
      });
      D.put(KEY, all);
      return;
    }

    // 1) local rules (stock, FAQ, "talk to staff")
    var local = localReply(text);
    if (local) {
      setTimeout(function () {
        add(local.text, "bot");
        if (local.esc) escalate(text);
      }, 300);
      return;
    }

    // 2) Gemini via chatbot.php
    busy = true;
    var typing = add("Typing...", "bot");
    askAI(text)
      .then(function (answer) {
        typing.textContent = answer;
      })
      .catch(function (err) {
        console.error("Chatbot AI error:", err);
        // 3) AI unavailable: hand the question to the team
        typing.textContent =
          "I can't answer that right now, so I'm connecting you with a Densco team member. They will reply right here.";
        escalate(text);
      })
      .then(function () {
        busy = false;
        msgs.scrollTop = msgs.scrollHeight;
      });
  }

  document.getElementById("cbFab").onclick = function () {
    cb.classList.add("open");
    input.focus();
  };
  document.getElementById("cbX").onclick = function () {
    cb.classList.remove("open");
  };
  document.getElementById("cbForm").onsubmit = function (e) {
    e.preventDefault();
    var t = input.value.trim();
    if (t) {
      input.value = "";
      send(t);
    }
  };
  document.getElementById("cbChips").onclick = function (e) {
    if (e.target.tagName === "BUTTON") send(e.target.textContent);
  };
  setInterval(function () {
    if (!active) return;
    var q = find(active);
    if (!q) return;
    for (var i = seen; i < q.messages.length; i++)
      if (q.messages[i].sender === "staff") add(q.messages[i].text, "staff");
    seen = q.messages.length;
  }, POLL);
})();