/* Densco — shared prototype data layer (localStorage stands in for a backend) */
(function () {
  "use strict";
  var D = {};
  function get(k, d) {
    try {
      var v = localStorage.getItem(k);
      return v ? JSON.parse(v) : d;
    } catch (e) {
      return d;
    }
  }
  function put(k, v) {
    try {
      localStorage.setItem(k, JSON.stringify(v));
    } catch (e) {}
  }
  D.get = get;
  D.put = put;
  D.esc = function (s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
      }[c];
    });
  };
  D.peso = function (n) {
    return (
      "\u20B1" +
      Number(n).toLocaleString("en-US", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })
    );
  };
  // Friendly "added to cart" message: names the product and links to the cart.
  // added = units actually added (0 = already at the stock limit).
  D.cartNotice = function (id, asked, added) {
    var p = D.products().filter(function (x) {
        return x.id === id;
      })[0],
      name = p ? p.name : "Item",
      t = document.createElement("div"),
      msg = document.createElement("span");
    t.className = "toast toast-cart" + (added > 0 ? "" : " toast-warn");
    t.setAttribute("role", "status");
    if (added <= 0)
      msg.textContent =
        "Not added: your cart already has all " + (p ? p.stock : "available") + " units of " + name + " in stock.";
    else if (added < asked)
      msg.textContent =
        "Only " + added + " more of " + name + " added: that is all the stock available.";
    else
      msg.textContent =
        "\u2713 " + (added > 1 ? added + " \u00D7 " : "") + name + " added to your cart.";
    t.appendChild(msg);
    var a = document.createElement("a");
    a.href = "cart.php";
    a.textContent = "View cart";
    t.appendChild(a);
    document.body.appendChild(t);
    setTimeout(function () {
      t.remove();
    }, 4200);
  };

  D.toast = function (m) {
    var t = document.createElement("div");
    t.className = "toast";
    t.textContent = m;
    document.body.appendChild(t);
    setTimeout(function () {
      t.remove();
    }, 2600);
  };

  D.categories = [
    "Stretcher / Ambulance",
    "Table",
    "Cart",
    "Lighting",
    "Panel Screen",
    "Stool",
    "Sink",
    "OB Accessory",
    "Oxygen Cart",
    "Oxygen Holder",
    "Basin/Pail",
    "Negatoscope",
    "Stretcher",
    "Mayo Stand",
    "Mayo Tray",
    "Cabinet",
    "Food Conveyor",
    "Hospital Bed",
    "OB/Delivery Table",
    "Chair",
    "Bedpan",
    "Chart Holder",
    "IV Stand",
    "Bucket",
    "Hamper",
    "Sterilizer",
    "Baby Bassinet",
    "Baby Crib"
  ];
  D.settings = function () {
    var s = get("denscoSettings", {});
    s.lowStock = +s.lowStock || 10;
    s.deliveryFee =
      s.deliveryFee === undefined || s.deliveryFee === ""
        ? 120
        : +s.deliveryFee;
    return s;
  };
  // Business details shown on the home page, footer and chatbot.
  // >>> Fill in the three empty values below with the real ones. <<<
  //   viber    : Viber number, e.g. "0917 123 4567"
  //   facebook : full link to the Facebook page, e.g. "https://www.facebook.com/yourpage"
  //   mapUrl   : Google Maps link to the exact location (optional, adds a "View on map" link)
  // They can also be changed by the Owner in Settings (saved in that browser only).
  var BIZ_DEFAULTS = {
    address: "Quezon City, Metro Manila, Philippines",
    hours: "Monday to Saturday, 8:00 AM to 6:00 PM",
    contact: "Viber, Messenger and SMS",
    viber: "",
    facebook: "",
    mapUrl: "",
  };
  D.biz = function () {
    var s = D.settings(),
      o = {};
    Object.keys(BIZ_DEFAULTS).forEach(function (k) {
      o[k] = s[k] || BIZ_DEFAULTS[k];
    });
    return o;
  };
  // Only http(s) links are used for the Facebook / map buttons.
  D.safeUrl = function (u) {
    return /^https?:\/\//i.test(u || "") ? u : "";
  };
  // "0917 123 4567" -> "+639171234567" (for viber:// and tel: links)
  D.viberDigits = function (v) {
    var d = String(v || "").replace(/[^\d+]/g, "");
    if (/^0\d{10}$/.test(d)) d = "+63" + d.slice(1);
    else if (/^63\d{10}$/.test(d)) d = "+" + d;
    return d;
  };

  // Products always come from the database: each PHP page sets window.DHP_PRODUCTS
  // before this script runs. Pages without it get an empty list (no fake placeholder data).
  D.products = function () {
    return window.DHP_PRODUCTS || [];
  };
  D.saveProducts = function (p) {
    put("denscoProducts", p);
  };
  D.state = function (p) {
    return p.stock <= 0
      ? "out"
      : p.stock <= D.settings().lowStock
        ? "low"
        : "in";
  };
  D.stateLabel = function (p) {
    var s = D.state(p);
    return s === "out"
      ? "Out of Stock"
      : s === "low"
        ? p.stock + " units left"
        : p.stock + " units available";
  };

  // The storefront and Staff Inventory use the quantity on hand.
  D.canOrder = function (p) {
    return p.stock > 0;
  };

  // Best-Selling Products: ranked by units actually sold (order_items),
  // which each PHP page attaches to every product as `sold`. Only
  // products the customer can currently buy are offered here, so the
  // "add to cart" action in this section is never a dead end.
  D.bestSellers = function (limit) {
    limit = limit || 8;
    return D.products()
      .filter(function (p) {
        return !p.hidden && D.canOrder(p) && p.sold > 0;
      })
      .sort(function (a, b) {
        return b.sold - a.sold;
      })
      .slice(0, limit);
  };

  D.cart = function () {
    return get("denscoCart", []);
  };
  D.saveCart = function (c) {
    put("denscoCart", c);
    if (D.updateBadge) D.updateBadge();
  };
  D.cartCount = function () {
    return D.cart().reduce(function (n, i) {
      return n + i.qty;
    }, 0);
  };
  // Adds q units of product id to the cart, never going above the stock on hand
  // (checkout would reject the order otherwise). Returns the number of units
  // actually added: 0 means the cart already holds all the available stock.
  D.addToCart = function (id, q) {
    var c = D.cart(),
      f = c.filter(function (i) {
        return i.id === id;
      })[0],
      p = D.products().filter(function (x) {
        return x.id === id;
      })[0],
      max = p && typeof p.stock === "number" ? Math.max(0, p.stock) : Infinity,
      have = f ? f.qty : 0,
      next = Math.min(have + q, max),
      added = Math.max(0, next - have);
    if (added > 0) {
      if (f) f.qty = next;
      else c.push({ id: id, qty: next });
      D.saveCart(c);
    }
    return added;
  };

  D.user = function () {
    return get("denscoUser", null);
  };
  D.login = function (n) {
    put("denscoUser", n || "Dr. Maria Clara");
  };
  D.logout = function () {
    try {
      localStorage.removeItem("denscoUser");
    } catch (e) {}
  };

  // No demo orders: the order history starts empty until real orders are placed.
  var ORDERS = [];
  D.orders = function () {
    var o = get("denscoOrdersV2", null);
    if (!o) {
      o = ORDERS;
      put("denscoOrdersV2", o);
    }
    return o;
  };
  D.saveOrders = function (o) {
    put("denscoOrdersV2", o);
  };
  D.statusLabel = function (o) {
    return {
      pending: "Pending",
      confirmed: "Confirmed",
      shipped:
        o.fulfillment === "Store Pickup"
          ? "Ready for pickup"
          : "Out for delivery",
      completed: "Completed",
      cancelled: "Cancelled",
    }[o.status] || "Pending";
  };

  var SALES = [
    [
      "DHP-10231",
      "online",
      "Dr. Maria Clara",
      "Sep 5, 2026",
      "3 lines (BP Monitor, Gloves, Wheelchair)",
      8420,
      "GCash",
    ],
    [
      "POS-2216",
      "walkin",
      "Walk-in customer",
      "Sep 5, 2026",
      "2 items (Masks, Alcohol)",
      340,
      "Cash",
    ],
    [
      "DHP-10198",
      "online",
      "Dr. Maria Clara",
      "Aug 28, 2026",
      "1 item (Wheelchair)",
      4200,
      "Cash",
    ],
    [
      "POS-2201",
      "walkin",
      "Walk-in customer",
      "Aug 28, 2026",
      "1 item (Thermometer)",
      320,
      "Cash",
    ],
    [
      "DHP-10177",
      "online",
      "Dr. Maria Clara",
      "Aug 20, 2026",
      "3 lines (Gloves, Masks, Alcohol)",
      12900,
      "Bank Transfer",
    ],
    [
      "POS-2188",
      "walkin",
      "Walk-in customer",
      "Aug 18, 2026",
      "1 item (Pulse Oximeter)",
      990,
      "Cash",
    ],
    [
      "DHP-10160",
      "online",
      "HealthFirst Laboratory",
      "Aug 14, 2026",
      "2 items (Thermometer, Oximeter)",
      3050,
      "GCash",
    ],
    [
      "POS-2170",
      "walkin",
      "Walk-in customer",
      "Aug 12, 2026",
      "3 items (Gloves, Masks)",
      1240,
      "Cash",
    ],
    [
      "POS-2165",
      "walkin",
      "Walk-in customer",
      "Aug 10, 2026",
      "1 item (N95 Respirator)",
      300,
      "Cash",
    ],
  ].map(function (r) {
    return {
      id: r[0],
      ch: r[1],
      who: r[2],
      date: r[3],
      items: r[4],
      total: r[5],
      pay: r[6],
    };
  });
  D.sales = function () {
    return SALES;
  };

  window.DHP = D;
})();