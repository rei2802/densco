/* Densco — restock request flow: Inventory submits, Admin reviews, Owner gives final approval */
(function () {
  "use strict";
  var D = window.DHP,
    KEY = "denscoRestockRequests";

  D.restockRequests = function () {
    return D.get(KEY, []);
  };
  D.saveRestockRequests = function (v) {
    D.put(KEY, v);
  };

  // status -> [badge class, label]
  D.restockBadge = function (status) {
    return (
      {
        pending: ["pending", "Pending Admin Review"],
        admin_approved: ["confirmed", "Pending Owner Approval"],
        approved: ["completed", "Approved"],
        rejected: ["out", "Rejected"],
      }[status] || ["", status]
    );
  };

  // Inventory Staff submits a new request. Returns {ok:true} or {ok:false, error:"..."}
  D.requestRestock = function (productId, qty, reason) {
    var p = D.products().find(function (x) {
      return x.id === productId;
    });
    if (!p) return { ok: false, error: "Product not found." };
    if (!qty || qty < 1)
      return { ok: false, error: "Enter a valid requested quantity." };
    if (!reason || !reason.trim())
      return { ok: false, error: "Enter a reason or note for this request." };

    var R = D.restockRequests();
    var dupe = R.some(function (r) {
      return (
        r.productId === productId &&
        (r.status === "pending" || r.status === "admin_approved")
      );
    });
    if (dupe)
      return {
        ok: false,
        error: "A restock request for this product is already in progress.",
      };

    R.unshift({
      id: "RR-" + Date.now(),
      productId: p.id,
      productName: p.name,
      currentStock: p.stock,
      requestedQty: qty,
      reason: reason.trim(),
      requestedBy: D.roleName,
      date: new Date().toLocaleDateString("en-US", {
        month: "short",
        day: "numeric",
        year: "numeric",
      }),
      status: "pending",
      adminRemarks: "",
      adminBy: "",
      adminDate: "",
      ownerRemarks: "",
      ownerBy: "",
      ownerDate: "",
    });
    D.saveRestockRequests(R);
    return { ok: true };
  };

  // Admin or Owner approves/rejects a request, based on the currently logged-in D.role.
  // outcome: "approved" | "rejected". Returns {ok:true} or {ok:false, error:"..."}
  D.reviewRestock = function (id, outcome, remarks) {
    var R = D.restockRequests(),
      r = R.find(function (x) {
        return x.id === id;
      });
    if (!r) return { ok: false, error: "Request not found." };

    var today = new Date().toLocaleDateString("en-US", {
      month: "short",
      day: "numeric",
      year: "numeric",
    });

    if (D.role === "admin") {
      if (r.status !== "pending")
        return {
          ok: false,
          error: "This request is not awaiting admin review.",
        };
      r.adminRemarks = (remarks || "").trim();
      r.adminBy = D.roleName;
      r.adminDate = today;
      r.status = outcome === "approved" ? "admin_approved" : "rejected";
    } else if (D.role === "owner") {
      if (r.status !== "admin_approved")
        return {
          ok: false,
          error: "This request is not awaiting owner approval.",
        };
      r.ownerRemarks = (remarks || "").trim();
      r.ownerBy = D.roleName;
      r.ownerDate = today;
      r.status = outcome === "approved" ? "approved" : "rejected";
    } else {
      return {
        ok: false,
        error: "Inventory Staff cannot approve or reject requests.",
      };
    }

    D.saveRestockRequests(R);
    return { ok: true };
  };

  // Can the current role act (review) on this request right now?
  D.canReviewRestock = function (r) {
    if (D.role === "admin") return r.status === "pending";
    if (D.role === "owner") return r.status === "admin_approved";
    return false;
  };
})();
