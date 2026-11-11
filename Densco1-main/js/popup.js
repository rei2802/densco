/* Densco - order confirmation pop-ups (order placed / order cancelled).
   Uses the site's existing .modal styles; extra .pop-* styles live in css/style.css.
   Usage: DHP.orderPopup({ type: "placed" | "cancelled", orderId: "DHP-00001",
                           items: [{ name: "Product", qty: 2 }], onClose: fn }) */
(function () {
  "use strict";
  var D = window.DHP;

  var ICON_OK =
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
  var ICON_BAD =
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>';

  D.orderPopup = function (o) {
    var placed = o.type === "placed";
    var title = placed ? "Order placed successfully" : "Order cancelled";
    var sub = placed
      ? "Thank you! We received your order and it is now pending confirmation."
      : "Your order has been cancelled.";
    var btnLabel = placed ? "View my orders" : "OK";

    var items = (o.items || [])
      .map(function (i) {
        return (
          '<div class="pop-item"><span>' + D.esc(String(i.name)) + "</span>" +
          (i.qty ? '<span class="muted">×' + (parseInt(i.qty, 10) || 0) + "</span>" : "") +
          "</div>"
        );
      })
      .join("");

    var el = document.createElement("div");
    el.className = "modal open";
    el.setAttribute("role", "dialog");
    el.setAttribute("aria-modal", "true");
    el.setAttribute("aria-labelledby", "popTitle");
    el.innerHTML =
      '<div class="modal-box pop-box">' +
        '<div class="pop-ico ' + (placed ? "ok" : "bad") + '">' + (placed ? ICON_OK : ICON_BAD) + "</div>" +
        '<h3 id="popTitle">' + title + "</h3>" +
        '<p class="pop-sub">' + sub + "</p>" +
        '<div class="pop-det">' +
          '<div class="pop-row"><span class="pop-lbl">Order ID</span><span class="pop-val">' + D.esc(String(o.orderId || "")) + "</span></div>" +
          '<div class="pop-sep"></div>' +
          '<div class="pop-lbl">Product Name</div>' +
          '<div class="pop-items">' + items + "</div>" +
        "</div>" +
        '<button class="btn btn-red btn-block" type="button">' + btnLabel + "</button>" +
      "</div>";
    document.body.appendChild(el);

    var closed = false;
    function close() {
      if (closed) return;
      closed = true;
      document.removeEventListener("keydown", onKey);
      el.remove();
      if (o.onClose) o.onClose();
    }
    function onKey(e) {
      if (e.key === "Escape") close();
    }
    el.addEventListener("click", function (e) {
      if (e.target === el || e.target.closest(".btn")) close();
    });
    document.addEventListener("keydown", onKey);
    el.querySelector(".btn").focus();
  };
})();