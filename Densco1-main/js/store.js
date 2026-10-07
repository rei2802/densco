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
  D.toast = function (m) {
    var t = document.createElement("div");
    t.className = "toast";
    t.textContent = m;
    document.body.appendChild(t);
    setTimeout(function () {
      t.remove();
    }, 2600);
  };

  D.categories = ["Diagnostics", "PPE", "Mobility Aids", "Consumables"];
  D.settings = function () {
    var s = get("denscoSettings", {});
    s.lowStock = +s.lowStock || 10;
    s.deliveryFee =
      s.deliveryFee === undefined || s.deliveryFee === ""
        ? 120
        : +s.deliveryFee;
    return s;
  };
  D.biz = function () {
    var s = D.settings();
    return {
      address: s.address || "Quezon City, Metro Manila, Philippines",
      hours: s.hours || "Monday to Saturday, 8:00 AM to 6:00 PM",
      contact: s.contact || "Viber, Messenger and SMS",
    };
  };

  var PRODUCTS = [
    {
      id: 1,
      name: "Digital Blood Pressure Monitor",
      cat: "Diagnostics",
      price: 1850,
      stock: 46,
      desc: "Automatic upper-arm monitor for clinic and home use, with irregular heartbeat detection, dual-user memory and a large backlit display.",
    },
    {
      id: 2,
      name: "Disposable Surgical Gloves (Box of 100)",
      cat: "PPE",
      price: 450,
      stock: 120,
      desc: "Powder-free disposable gloves for clinical, laboratory and general medical use.",
    },
    {
      id: 3,
      name: "Folding Wheelchair \u2014 Standard",
      cat: "Mobility Aids",
      price: 4200,
      stock: 18,
      desc: "Lightweight foldable wheelchair with padded seat, footrests and hand brakes.",
    },
    {
      id: 4,
      name: "N95 Medical Respirator (Pack of 10)",
      cat: "PPE",
      price: 650,
      stock: 3,
      desc: "N95-rated respirators with an adjustable nose clip for high-filtration protection.",
    },
    {
      id: 5,
      name: "Digital Clinical Thermometer",
      cat: "Diagnostics",
      price: 320,
      stock: 9,
      desc: "Fast-read digital thermometer with fever alert and memory recall.",
    },
    {
      id: 6,
      name: "Isopropyl Alcohol 70% (1 Liter)",
      cat: "Consumables",
      price: 95,
      stock: 4,
      desc: "70% isopropyl alcohol for surface and skin disinfection.",
    },
    {
      id: 7,
      name: "3-Ply Surgical Face Masks (Box of 50)",
      cat: "PPE",
      price: 150,
      stock: 60,
      desc: "Three-layer disposable masks with elastic ear loops.",
    },
    {
      id: 8,
      name: "Fingertip Pulse Oximeter",
      cat: "Diagnostics",
      price: 990,
      stock: 25,
      desc: "Compact oximeter showing SpO2 and pulse rate on an OLED display.",
    },
  ];
  // D.products() now prefers live database data injected by PHP pages
  // (catalog.php / product.php / cart.php set window.DHP_PRODUCTS before this script runs).
  // Pages not yet converted to .php (like index.html) still fall back to the old localStorage copy.
  D.products = function () {
    if (window.DHP_PRODUCTS) return window.DHP_PRODUCTS;
    var p = get("denscoProducts", null);
    if (!p) {
      p = PRODUCTS;
      put("denscoProducts", p);
    }
    return p;
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
      ? "Out of stock"
      : s === "low"
        ? p.stock + " units left"
        : p.stock + " units available";
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
  D.addToCart = function (id, q) {
    var c = D.cart(),
      f = c.filter(function (i) {
        return i.id === id;
      })[0];
    if (f) f.qty += q;
    else c.push({ id: id, qty: q });
    D.saveCart(c);
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
    }[o.status];
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