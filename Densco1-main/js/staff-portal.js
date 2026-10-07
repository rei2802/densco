/* Densco — staff portal shell: role login, role-based sidebar, access guard, modals */
(function () {
  "use strict";

  var D = window.DHP;
  var body = document.body;
  var page = body.getAttribute("data-page");

  var ROLES = {
    owner: "Business Owner",
    admin: "Administrator",
    inventory: "Inventory Staff"
  };

  var PORTAL = {
    owner: "OWNER PORTAL",
    admin: "ADMIN PORTAL",
    inventory: "INVENTORY PORTAL"
  };

  var NAV = [
    ["dashboard", "Dashboard", "owner admin inventory"],
    ["orders", "Orders", "owner admin"],
    ["inventory", "Inventory", "owner admin inventory"],
    ["restock", "Restock Requests", "owner admin inventory"],
    ["inquiries", "Inquiries", "admin"],
    ["sales-records", "Sales & Records", "owner admin"],
    ["staff", "Staff Accounts", "owner admin"],
    ["reports", "Reports", "owner"],
    ["settings", "Settings", "owner"]
  ];

  // Pages converted to real PHP/database pages so far. Anything not listed here
  // still defaults to .html. Add to this list as more staff pages get converted.
  var PHP_PAGES = { inventory: true, orders: true };

  function ext(key) {
    return PHP_PAGES[key] ? ".php" : ".html";
  }

  D.applyRoles = function (root) {
    (root || document).querySelectorAll("[data-roles]").forEach(function (el) {
      if (el === body) return;
      if (el.getAttribute("data-roles").split(" ").indexOf(D.role) < 0) {
        el.classList.add("hide");
      }
    });
  };

  document.addEventListener("click", function (e) {
    var o = e.target.closest("[data-open]");
    if (o) {
      e.preventDefault();
      document.getElementById(o.getAttribute("data-open")).classList.add("open");
    }

    var c = e.target.closest("[data-close]");
    if (c) c.closest(".modal").classList.remove("open");

    if (e.target.classList.contains("modal")) {
      e.target.classList.remove("open");
    }
  });

  if (page === "login") {
    var f = document.getElementById("staffLogin");
    f.addEventListener("submit", function (e) {
      e.preventDefault();
      var err = document.getElementById("formErr");
      var u = f.elements.user.value.trim(),
        p = f.elements.pw.value;

      if (!u || !p) {
        err.textContent = "Enter your staff email or username and your password.";
        return;
      }
      err.textContent = "";

      fetch("login.php", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ user: u, pw: p })
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (data) {
          if (!data.success) {
            err.textContent = data.error || "Login failed. Please try again.";
            return;
          }
          if (!ROLES[data.role]) {
            err.textContent = "This account does not have a valid staff role.";
            return;
          }
          if (data.role !== f.elements.role.value) {
            err.textContent = "This is not a " + ROLES[f.elements.role.value] + " account.";
            return;
          }
          D.put("denscoStaffRole", data.role);
          location.href = "dashboard.html";
        })
        .catch(function () {
          err.textContent = "Something went wrong. Please try again.";
        });
    });
    return;
  }

  var role = D.get("denscoStaffRole", null);
  if (!ROLES[role]) {
    location.replace("login.php");
    return;
  }

  D.role = role;
  D.roleName = ROLES[role];

  var allowed = (body.getAttribute("data-roles") || "").split(" ");
  if (allowed[0] && allowed.indexOf(role) < 0) {
    location.replace("dashboard.html");
    return;
  }

  var side = document.getElementById("sidebar");

  side.innerHTML =
    '<nav class="side-nav">' +
      NAV.filter(function (n) {
        return n[2].split(" ").indexOf(role) > -1;
      }).map(function (n) {
        return '<a href="' + n[0] + ext(n[0]) + '"' + (n[0] === page ? ' class="on"' : "") + ">" + n[1].replace("&", "&amp;") + "</a>";
      }).join("") +
    '</nav>' +
    '<div class="side-foot">' +
      '<div><small>Signed in as</small><b>' + ROLES[role] + '</b></div>' +
      '<a href="login.php" id="staffLogout">Log Out</a>' +
      '<a class="tiny" href="../index.html">Back to main website</a>' +
    '</div>';

  document.getElementById("staffLogout").addEventListener("click", function (e) {
    e.preventDefault();
    try {
      localStorage.removeItem("denscoStaffRole");
    } catch (x) {}
    location.href = "login.php?logout=1";
  });

  document.querySelector(".app-main").insertAdjacentHTML(
    "afterbegin",
    '<div class="mobilebar"><b>DENSCO</b><button class="btn btn-red btn-sm" id="sideBtn">Menu</button></div>'
  );

  document.getElementById("sideBtn").addEventListener("click", function () {
    side.classList.toggle("open");
  });

  D.applyRoles(document);
})();