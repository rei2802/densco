/* Densco Chatbot
   - Local FAQ responses
   - Live product stock lookup
   - Gemini AI responses through api/chatbot.php
   - Escalation to staff when requested or AI is unavailable
   - Visible indicator whenever the customer is talking to staff
*/

(function () {
  "use strict";

  var D = window.DHP;
  var POLL = 2000;

  // Path to the PHP file, worked out from where THIS script was loaded
  // (<site>/js/chatbot.js -> <site>/api/chatbot.php). It therefore works from
  // any page and under any folder name (XAMPP sub-folder or domain root).
  var API_URL = (function () {
    try {
      var src = document.currentScript && document.currentScript.src;
      if (src) return new URL("../api/chatbot.php", src).href;
    } catch (e) {}
    return "/api/chatbot.php";
  })();
  var STAFF_URL = new URL("staff-chat.php", new URL(API_URL, location.href));

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

  // The local sign-in state is used for assistant history and copy. PHP authorizes staff chat.
  function isLoggedIn() {
    return whoKey !== GUEST;
  }
  var awaitingProductName = false;
  var busy = false;

  // Remembers the last few messages so the AI can follow the conversation
  var history = [];

  function remember(role, text) {
    history.push({ role: role, text: text });
    if (history.length > 12) history.shift();
  }

  function categoryList() {
    try { return (D.categories || []).join(", "); } catch (e) { return ""; }
  }

  // Gives the AI your real business info, live stock levels and policies
  function buildContext() {
    try {
      var biz = D.biz();
      var lines = D.products().map(function (product) {
        var line = "- " + product.name + " (" + (product.cat || "General") + "): " + D.stateLabel(product);
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
        "Payment: GCash / QR PH and cash. GCash orders need the payment reference number entered at checkout.\n" +
        "Warranty: Most items carry a 1-year manufacturer warranty. For returns or replacements, customers message Densco on Viber or Messenger.\n" +
        "Customer account: " +
        (isLoggedIn()
          ? "logged in. The customer can type 'talk to staff' to chat with the Densco team."
          : "guest, not logged in. Staff chat is only available after logging in, so ask the customer to log in first or to contact Densco on Viber or Messenger.") +
        "\n" +
        "Website guide (how customers navigate the site):\n" +
        "- Home page: search bar, browse by category, and business information.\n" +
        "- Catalog page: shows all products. Customers can filter by category (" + categoryList() + ") or search by product name. For example, the Stretcher / Ambulance category lists stretchers.\n" +
        "- Product page: click any product name or image to see its price, description, live stock and Add to Cart.\n" +
        "- Cart and Checkout: add items to the cart, then check out and choose delivery or store pickup and a payment method.\n" +
        "- My Account / My Orders: track order status from Pending to Completed.\n" +
        "- Login and Register: needed for placing orders and chatting with staff.\n\n" +
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
      "We accept GCash / QR PH and cash. For GCash, scan the QR code at checkout and enter the 12 to 13 character reference number from your receipt."
    ],
    [
      /order|status|track/,
      "You may track your order status, from Pending to Completed, under My Account."
    ],
    [
      /business hours|opening hours|store hours|are you open|open (today|on|until|at)|what time|when do you (open|close)|closing|location|address|located|where are you/,
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

    // General product-knowledge questions ("what is a nebulizer?", "what is it used for?")
    // go to Gemini, unless the customer is really asking about price or stock
    var asksInfo = /what is|what's|what are|what does|what do|ano (ang|ba)|ano po|explain|describe|meaning of|used for|use of|uses of|purpose|how does|how do|difference between|tell me about|benefits|features/.test(text);
    var asksBusinessFact = /price|cost|how much|magkano|stock|available|availability|inventory|how many/.test(text);

    if (asksInfo && !asksBusinessFact) {
      return null;
    }

    // Website navigation questions go to Gemini, which has the site guide
    if (/navigate|find|see it|where can|where do|where is|how do i|how can i|browse|which page|what page|website|link|go to|click|menu|section|categor/.test(text)) {
      return null;
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
            '<small id="cbSub">Answers instantly, or hands you to our team</small>' +
          '</div>' +
          '<button class="cb-x" id="cbX" aria-label="Close chat">&times;</button>' +
        '</div>' +
        '<div class="cb-staffbar" id="cbStaff" hidden>' +
          '<span class="cb-dot"></span>' +
          '<span id="cbStaffState" role="status"></span>' +
          '<button type="button" id="cbReopen" hidden>Request staff</button>' +
          '<button type="button" id="cbBack">Back to bot</button>' +
        '</div>' +
        '<div class="cb-msgs" id="cbMsgs">' +
          '<div class="cb-m bot">' + GREETING + '</div>' +
        '</div>' +
        '<div class="cb-msgs" id="cbStaffMsgs" role="log" aria-label="Staff conversation messages" hidden></div>' +
        '<div class="cb-chips" id="cbChips">' +
          '<button>Check stock</button>' +
          '<button>Delivery and pickup</button>' +
          '<button>Payment options</button>' +
          '<button type="button" id="cbTalk">Talk to staff</button>' +
        '</div>' +
        '<p id="cbStaffError" role="status" hidden></p>' +
        '<form class="cb-form" id="cbForm">' +
          '<input id="cbIn" placeholder="Type a message" autocomplete="off">' +
          '<button type="button" id="cbMic" class="cb-mic" aria-label="Hold to talk" title="Hold to talk">' +
            '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' +
              '<rect x="9" y="3" width="6" height="11" rx="3"/>' +
              '<path d="M5 11a7 7 0 0 0 14 0"/>' +
              '<path d="M12 18v3"/>' +
              '<path d="M9 21h6"/>' +
            '</svg>' +
          '</button>' +
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

  var staffBar = document.getElementById("cbStaff");
  var staffState = document.getElementById("cbStaffState");
  var staffMsgs = document.getElementById("cbStaffMsgs");
  var staffError = document.getElementById("cbStaffError");
  var reopen = document.getElementById("cbReopen");
  var chips = document.getElementById("cbChips");
  var talk = document.getElementById("cbTalk");
  var form = document.getElementById("cbForm");
  var sub = document.getElementById("cbSub");
  var SUB_BOT = sub.textContent;
  var staffThread = null;
  var staffCursor = 0;
  var staffPaused = false;
  var staffAvailable = false;
  var staffAccess = true;
  var staffReading = false;
  var staffSaving = false;
  var staffVersion = 0;
  var pendingStaffMessage = null;

  // Styles are injected here so no CSS file edit is needed
  // (move them to your stylesheet if you prefer)
  var st = document.createElement("style");
  st.textContent =
    ".cb-staffbar{display:flex;align-items:center;gap:8px;padding:8px 14px;background:#e8f7ee;color:#14633a;font-size:13px;border-bottom:1px solid #c6ebd5}" +
    ".cb-win [hidden]{display:none}" +
    ".cb-staffbar span:nth-child(2){flex:1}" +
    ".cb-staffbar button{border:1px solid #14633a;background:#fff;color:#14633a;border-radius:12px;padding:2px 10px;font-size:12px;cursor:pointer}" +
    ".cb-dot{width:9px;height:9px;border-radius:50%;background:#22c55e;animation:cbPulse 1.5s infinite}" +
    "@keyframes cbPulse{0%,100%{opacity:1}50%{opacity:.35}}" +
    ".cb-m.staff{border-left:3px solid #22c55e}" +
    "#cbStaffError{margin:0;padding:7px 14px;background:#fff4e5;color:#793f00;font-size:13px}";
  document.head.appendChild(st);

  function staffMode() {
    return staffThread && !staffPaused;
  }

  function updateStaffIndicator() {
    var onStaff = staffMode();
    staffBar.hidden = !onStaff;
    staffMsgs.hidden = !onStaff;
    msgs.hidden = !!onStaff;
    chips.hidden = !!onStaff;
    talk.textContent = staffThread ? "View staff conversation" : "Talk to staff";
    sub.textContent = onStaff ? "Densco staff conversation" : SUB_BOT;
    var ended = onStaff && staffThread.status === "closed";
    reopen.hidden = !ended;
    form.hidden = !!ended;
    if (onStaff) {
      staffState.textContent = ended
        ? "Conversation ended."
        : staffThread.status === "answered"
        ? "Staff replied."
        : staffAvailable
        ? "Waiting for a staff reply."
        : "No staff are online. Your message is queued.";
    }
  }

  document.getElementById("cbBack").onclick = function () {
    returnToBot();
  };
  reopen.onclick = function () { sendToStaff("request", "Talk to staff"); };

  // ---- Chat storage -------------------------------------------------
  // Logged-in customers: the chat is saved under their account and is still
  // there after a refresh or the next time they log in.
  // Guests: nothing is saved. A refresh starts a brand new chat.
  function storageKey() {
    return "denscoChat:" + whoKey;
  }

  // Staff history is kept only on the server. Save assistant history and view preference here.
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
          staffPaused: staffPaused
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
    staffVersion++;
    staffThread = null;
    staffCursor = 0;
    staffPaused = false;
    staffAvailable = false;
    staffAccess = true;
    pendingStaffMessage = null;
    staffMsgs.replaceChildren();
    showStaffError("");
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

      staffPaused = saved.staffPaused === true;
    } else {
      add(GREETING, "bot", true);
    }

    updateStaffIndicator();
  }

  loadChat();

  function showStaffError(message) {
    staffError.textContent = message;
    staffError.hidden = !message;
  }

  async function staffRequest(body) {
    var url = new URL(STAFF_URL);
    url.searchParams.set("after", staffCursor);
    if (staffThread) url.searchParams.set("known", staffThread.id);
    var response = await fetch(url, {
      method: body ? "POST" : "GET",
      credentials: "same-origin",
      headers: body ? { "Content-Type": "application/json" } : {},
      body: body ? JSON.stringify(body) : undefined,
      signal: AbortSignal.timeout(12000)
    });
    var result = await response.json();
    if (!response.ok) {
      var error = new Error(result.error || "Staff chat is unavailable.");
      error.status = response.status;
      throw error;
    }
    return result;
  }

  function receiveStaffChat(result) {
    if (staffThread?.id !== result.thread?.id) {
      staffCursor = 0;
      staffMsgs.replaceChildren();
    }
    staffThread = result.thread;
    staffAvailable = result.available;
    if (staffThread) {
      staffThread.messages.forEach(function (message) {
        if (message.id <= staffCursor) return;
        var element = document.createElement("div");
        element.className = "cb-m " + (message.sender === "staff" ? "staff" : "user");
        element.textContent = message.text;
        staffMsgs.appendChild(element);
        staffCursor = message.id;
      });
    }
    updateStaffIndicator();
    if (staffMode() && staffThread.messages.length) staffMsgs.scrollTop = staffMsgs.scrollHeight;
  }

  function reportStaffError(error, writing) {
    if (error.status === 401) {
      staffAccess = false;
      pendingStaffMessage = null;
      receiveStaffChat({ thread: null, available: false });
      if (writing || isLoggedIn()) showStaffError(LOGIN_MSG);
      return;
    }
    showStaffError(error.status ? error.message :
      writing ? "Could not confirm delivery. Try sending again." : "Staff chat could not connect. Reconnecting…");
  }

  async function refreshStaffChat() {
    if (staffReading || staffSaving || !staffAccess || document.hidden || !cb.classList.contains("open")) return;
    staffReading = true;
    var version = staffVersion;
    try {
      var result = await staffRequest();
      if (version === staffVersion) {
        receiveStaffChat(result);
        if (!pendingStaffMessage) showStaffError("");
      }
    } catch (error) {
      if (version === staffVersion) reportStaffError(error, false);
    } finally {
      staffReading = false;
      if (version !== staffVersion) refreshStaffChat();
    }
  }

  async function sendToStaff(action, text) {
    if (staffSaving) return;
    var request = pendingStaffMessage;
    if (!request || request.action !== action || request.text !== text) {
      var token = Array.from(crypto.getRandomValues(new Uint8Array(16)), function (byte) {
        return byte.toString(16).padStart(2, "0");
      }).join("");
      request = pendingStaffMessage = { action: action, text: text, token: token };
    }
    var version = ++staffVersion;
    staffSaving = true;
    reopen.disabled = talk.disabled = form.querySelector('[type="submit"]').disabled = true;
    showStaffError("");
    try {
      var result = await staffRequest(request);
      if (staffVersion !== version) return;
      staffAccess = true;
      staffPaused = false;
      pendingStaffMessage = null;
      receiveStaffChat(result);
      if (input.value.trim() === text) input.value = "";
      save();
    } catch (error) {
      if (staffVersion === version) {
        if (error.status === 409) pendingStaffMessage = null;
        reportStaffError(error, true);
      }
    } finally {
      staffSaving = false;
      reopen.disabled = talk.disabled = form.querySelector('[type="submit"]').disabled = false;
      refreshStaffChat();
    }
  }

  function returnToBot() {
    if (staffSaving) return;
    staffPaused = true;
    showStaffError("");
    updateStaffIndicator();
    save();
    input.focus();
  }

  function showOrRequestStaff(message) {
    if (staffThread) {
      staffPaused = false;
      updateStaffIndicator();
      staffMsgs.scrollTop = staffMsgs.scrollHeight;
      save();
      refreshStaffChat();
    } else {
      sendToStaff("request", message);
    }
  }

  function send(message) {
    var text = message.trim();
    if (/^(back to bot|bot|assistant)$/i.test(text)) {
      returnToBot();
      return;
    }
    if (staffMode()) {
      if (staffThread.status !== "closed") sendToStaff("send", text);
      return;
    }
    if (/\b(?:talk|speak|chat|connect)\b.{0,40}\b(?:staff|human|agent|representative|team member|person)\b|^(?:staff|human|agent|representative)(?: please)?[.!?]?$/i.test(text)) {
      showOrRequestStaff(text);
      return;
    }
    if (busy || staffSaving) return;
    input.value = "";
    add(text, "user");

    var local = looksEnglish(text) ? localReply(text) : null;
    if (local) {
      remember("user", text);
      remember("model", local.text);
      add(local.text, "bot");
      return;
    }

    busy = true;
    var account = whoKey;
    var typing = add("Typing...", "bot", true);

    askAI(text)
      .then(function (answer) {
        if (whoKey !== account) return;
        typing.textContent = answer;
        remember("user", text);
        remember("model", answer);
      })
      .catch(function (error) {
        if (whoKey !== account) return;
        console.error("Chatbot AI error:", error);
        typing.textContent = "I couldn’t answer that right now.";
        if (isLoggedIn() && !staffSaving && !staffMode()) {
          sendToStaff("request", text);
        } else if (!isLoggedIn()) {
          typing.textContent += " " + LOGIN_MSG;
        }
      })
      .then(function () {
        if (whoKey !== account) return;
        busy = false;
        msgs.scrollTop = msgs.scrollHeight;
        save();
      });
  }

  document.getElementById("cbFab").onclick = function () {
    cb.classList.add("open");
    input.focus();
    refreshStaffChat();
  };

  document.getElementById("cbX").onclick = function () {
    cb.classList.remove("open");
  };

  form.onsubmit = function (event) {
    event.preventDefault();
    var message = input.value.trim();
    if (message) send(message);
  };

  chips.onclick = function (event) {
    if (event.target === talk) {
      showOrRequestStaff("Talk to staff");
    } else if (event.target.tagName === "BUTTON") {
      send(event.target.textContent);
    }
  };
  // ===== VOICE CODE =====
  var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  var mic = document.getElementById("cbMic");

  if (!SR) {
    mic.style.display = "none";
  } else {
    var rec = new SR();
    var heard = "";
    var listening = false;
    var safety = null;

    rec.lang = "en-PH";            // "fil-PH" for Tagalog
    rec.interimResults = true;
    rec.continuous = true;

    function resetMic() {
      listening = false;
      mic.classList.remove("rec");
      clearTimeout(safety);
    }

    function stopMic() {
      if (!listening) return;
      try { rec.stop(); } catch (e) {}
      setTimeout(function () {
        if (listening) {
          try { rec.abort(); } catch (e) {}
          resetMic();
        }
      }, 1500);
    }

    rec.onresult = function (e) {
      heard = Array.prototype.map.call(e.results, function (r) {
        return r[0].transcript;
      }).join("");
      input.value = heard;
    };

    rec.onend = function () {
      var text = heard.trim();
      heard = "";
      resetMic();
      if (text) {
        input.value = "";
        send(text);
      }
    };

    rec.onerror = function () {
      heard = "";
      resetMic();
    };

    function startMic(e) {
      e.preventDefault();
      if (listening) return;
      heard = "";
      try {
        rec.start();
        listening = true;
        mic.classList.add("rec");
        if (mic.setPointerCapture && e.pointerId !== undefined) {
          try { mic.setPointerCapture(e.pointerId); } catch (err) {}
        }
        safety = setTimeout(stopMic, 30000);
      } catch (err) {
        resetMic();
      }
    }

    mic.addEventListener("pointerdown", startMic);
    mic.addEventListener("pointerup", stopMic);
    mic.addEventListener("pointercancel", stopMic);
    mic.addEventListener("lostpointercapture", stopMic);
    window.addEventListener("pointerup", stopMic);
    window.addEventListener("blur", stopMic);
    mic.addEventListener("contextmenu", function (e) { e.preventDefault(); });
  }
  // ===== END VOICE CODE =====


  function syncChat() {
    var nowUser = currentUser();
    if (userKey(nowUser) !== whoKey) {
      who = nowUser;
      whoKey = userKey(nowUser);
      loadChat();
    }
    refreshStaffChat();
  }

  document.addEventListener("visibilitychange", syncChat);
  window.addEventListener("storage", syncChat);
  setInterval(syncChat, POLL);
})();
