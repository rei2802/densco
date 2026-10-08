/* Densco — FAQ chatbot: auto-responses, live stock lookup, escalation to the staff inquiry queue */
(function () {
  "use strict";
  var D = window.DHP, KEY = "denscoInquiries", SESS = "denscoActiveInquiry", POLL = 4000;
  var who = D.user() || "Guest visitor";
  var ALIASES = { 1: ["blood pressure", "bp"], 2: ["glove"], 3: ["wheelchair"], 4: ["n95", "respirator"], 5: ["thermometer"], 6: ["alcohol", "isopropyl"], 7: ["mask"], 8: ["oximeter"] };
  var FAQ = [
    [/\b(hello|hi|hey)\b/, "Hello! I can check real-time stock, explain delivery and payment, or connect you with our team."],
    [/deliver|shipping|ship|pickup|pick up/, "Metro Manila delivery takes 1 to 2 days. You can also choose free store pickup at our Quezon City warehouse at checkout."],
    [/payment|pay|gcash|bank|proof|cash/, "We accept GCash / QR PH, bank transfer and cash. GCash and bank transfer orders need a proof of payment uploaded at checkout."],
    [/order|status|track/, "You can follow every order from Pending to Completed under My Account."],
    [/hours|open|location|address|where/, "We are open " + D.biz().hours.toLowerCase() + " at " + D.biz().address + "."],
    [/return|refund|warranty/, "Most items carry a 1-year manufacturer warranty. For returns or replacements, message us on Viber or Messenger."]
  ];
  function read() { return D.get(KEY, []); }
  function find(id) { return read().filter(function (i) { return i.id === id; })[0]; }
  function reply(t) {
    var l = t.toLowerCase();
    if (/staff|human|agent|person|representative|talk to/.test(l)) return { text: "I'll pass this to a Densco team member. They will answer right here.", esc: true };
    if (/stock|available|availability|inventory|how many/.test(l)) {
      var P = D.products();
      for (var i = 0; i < P.length; i++) for (var j = 0; j < ALIASES[P[i].id].length; j++) if (l.indexOf(ALIASES[P[i].id][j]) > -1)
        return { text: P[i].name + ": " + D.stateLabel(P[i]).toLowerCase() + "." };
      return { text: "Which product should I check? For example: blood pressure monitor, gloves, wheelchair, N95, thermometer, alcohol, masks or oximeter." };
    }
    for (var k = 0; k < FAQ.length; k++) if (FAQ[k][0].test(l)) return { text: FAQ[k][1] };
    return { text: "I can't answer that myself, so I'm connecting you with a Densco team member. They will reply right here.", esc: true };
  }

  document.body.insertAdjacentHTML("beforeend",
    '<div class="cb" id="cb"><div class="cb-win"><div class="cb-head"><div><b>Densco Assistant</b><small>Answers instantly, or hands you to our team</small></div><button class="cb-x" id="cbX" aria-label="Close chat">&times;</button></div>' +
    '<div class="cb-msgs" id="cbMsgs"><div class="cb-m bot">Hi! Ask about product stock, delivery, payment or your orders.</div></div>' +
    '<div class="cb-chips" id="cbChips"><button>Check stock</button><button>Delivery and pickup</button><button>Payment options</button><button>Talk to staff</button></div>' +
    '<form class="cb-form" id="cbForm"><input id="cbIn" placeholder="Type a message" autocomplete="off"><button class="btn btn-red btn-sm">Send</button></form></div>' +
    '<button class="cb-fab" id="cbFab" aria-label="Open chat"><svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M4 4H20V16H7L4 19V4Z" stroke="#fff" stroke-width="1.7" stroke-linejoin="round"/></svg></button></div>');

  var cb = document.getElementById("cb"), msgs = document.getElementById("cbMsgs"), input = document.getElementById("cbIn");
  var active = null, seen = 0;
  function add(t, s) { var m = document.createElement("div"); m.className = "cb-m " + s; m.textContent = t; msgs.appendChild(m); msgs.scrollTop = msgs.scrollHeight; }

  try { var sid = sessionStorage.getItem(SESS), ex = sid && find(sid); if (ex) { active = sid; ex.messages.forEach(function (m) { add(m.text, m.sender === "staff" ? "staff" : "user"); }); seen = ex.messages.length; } } catch (e) {}

  function send(text) {
  add(text, "user");

  if (active) {
    var all = read();

    all.forEach(function (i) {
      if (i.id === active) {
        i.messages.push({
          sender: "customer",
          text: text,
          time: new Date().toISOString()
        });

        seen = i.messages.length;
      }
    });

    D.put(KEY, all);
    return;
  }
console.log("CHATBOT FETCH STARTING");
  fetch("api/chatbot.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json"
    },
    body: JSON.stringify({
      message: text
    })
  })
  .then(function (response) {
    return response.json();
  })
  .then(function (data) {

    if (data.success) {
      add(data.message, "bot");
    } else {
      add("Sorry, I couldn't connect to the assistant right now.", "bot");
    }

  })
  .catch(function () {
    add("Sorry, there was a connection problem.", "bot");
  });
}
  document.getElementById("cbFab").onclick = function () { cb.classList.add("open"); input.focus(); };
  document.getElementById("cbX").onclick = function () { cb.classList.remove("open"); };
  document.getElementById("cbForm").onsubmit = function (e) { e.preventDefault(); var t = input.value.trim(); if (t) { input.value = ""; send(t); } };
  document.getElementById("cbChips").onclick = function (e) { if (e.target.tagName === "BUTTON") send(e.target.textContent); };
  setInterval(function () {
    if (!active) return; var q = find(active); if (!q) return;
    for (var i = seen; i < q.messages.length; i++) if (q.messages[i].sender === "staff") add(q.messages[i].text, "staff");
    seen = q.messages.length;
  }, POLL);
})();
