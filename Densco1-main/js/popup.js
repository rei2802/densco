/* Densco - order pop-ups: order placed, order cancelled, and the cancel confirmation.
   Uses the site's existing .modal styles; extra .pop-* styles live in css/style.css.

   DHP.orderPopup({ type: "placed" | "cancelled", orderId: "DHP-00001",
                    items: [{ name: "Product", qty: 2 }], onClose: fn })
   DHP.confirmCancelPopup({ orderId: "DHP-00001", items: [...], onConfirm: fn }) */
(function () {
  "use strict";
  var D = window.DHP;

  var ICONS = {
    ok: '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
    bad: '<path d="M6 6l12 12M18 6L6 18"/>',
    warn: '<path d="M12 7v6.5M12 17.2h.01"/>'
  };

  function icon(kind) {
    return (
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      ICONS[kind] + "</svg>"
    );
  }

  function itemsHtml(items) {
    return (items || [])
      .map(function (i) {
        return (
          '<div class="pop-item"><span>' + D.esc(String(i.name)) + "</span>" +
          (i.qty ? '<span class="muted">×' + (parseInt(i.qty, 10) || 0) + "</span>" : "") +
          "</div>"
        );
      })
      .join("");
  }

  /* cfg: kind (ok | bad | warn), title, sub, orderId, items,
          buttons: [{ key, label, cls }], focus: key of the button to focus,
          done(key): called once on close; key is null when dismissed (Esc / backdrop) */
  function show(cfg) {
    var el = document.createElement("div");
    el.className = "modal open";
    el.setAttribute("role", "dialog");
    el.setAttribute("aria-modal", "true");
    el.setAttribute("aria-labelledby", "popTitle");
    el.innerHTML =
      '<div class="modal-box pop-box">' +
        '<div class="pop-ico ' + cfg.kind + '">' + icon(cfg.kind) + "</div>" +
        '<h3 id="popTitle">' + cfg.title + "</h3>" +
        '<p class="pop-sub">' + cfg.sub + "</p>" +
        '<div class="pop-det">' +
          '<div class="pop-row"><span class="pop-lbl">Order ID</span><span class="pop-val">' + D.esc(String(cfg.orderId || "")) + "</span></div>" +
          '<div class="pop-sep"></div>' +
          '<div class="pop-lbl">Product Name</div>' +
          '<div class="pop-items">' + itemsHtml(cfg.items) + "</div>" +
        "</div>" +
        '<div class="pop-acts">' +
          cfg.buttons
            .map(function (b) {
              return '<button class="btn ' + b.cls + '" type="button" data-key="' + b.key + '">' + b.label + "</button>";
            })
            .join("") +
        "</div>" +
      "</div>";
    document.body.appendChild(el);

    var closed = false;
    function close(key) {
      if (closed) return;
      closed = true;
      document.removeEventListener("keydown", onKey);
      el.remove();
      cfg.done(key);
    }
    function onKey(e) {
      if (e.key === "Escape") close(null);
    }
    el.addEventListener("click", function (e) {
      var b = e.target.closest("[data-key]");
      if (b) close(b.dataset.key);
      else if (e.target === el) close(null);
    });
    document.addEventListener("keydown", onKey);
    (el.querySelector('[data-key="' + cfg.focus + '"]') || el.querySelector(".btn")).focus();
  }

  D.orderPopup = function (o) {
    var placed = o.type === "placed";
    show({
      kind: placed ? "ok" : "bad",
      title: placed ? "Order placed successfully" : "Order cancelled",
      sub: placed
        ? "Thank you! We received your order and it is now pending confirmation."
        : "Your order has been cancelled.",
      orderId: o.orderId,
      items: o.items,
      buttons: [{ key: "ok", label: placed ? "View my orders" : "OK", cls: "btn-red" }],
      focus: "ok",
      done: function () {
        if (o.onClose) o.onClose();
      }
    });
  };

  D.confirmCancelPopup = function (o) {
    show({
      kind: "warn",
      title: "Cancel this order?",
      sub: "This cannot be undone. Please confirm that you want to cancel it.",
      orderId: o.orderId,
      items: o.items,
      buttons: [
        { key: "keep", label: "Keep order", cls: "btn-line" },
        { key: "yes", label: "Yes, cancel order", cls: "btn-red" }
      ],
      focus: "keep",
      done: function (key) {
        if (key === "yes" && o.onConfirm) o.onConfirm();
      }
    });
  };
})();