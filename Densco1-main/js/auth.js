(function () {
  "use strict";
  var D = window.DHP;
  var page = location.pathname.split("/").pop() || "index.php";
  var user = D.user();

  if ((page === "cart.php" || page === "account.html") && !user) {
    location.replace("login.php?next=" + page);
    return;
  }

  var links = [
    ["index.php", "Home"],
    ["catalog.php", "Shop"],
  ];
  if (user) links.push(["cart.php", "Cart"], ["account.html", "My Account"]);
  var nav =
    links
      .map(function (l) {
        var on =
          l[0] === page || (page === "product.php" && l[0] === "catalog.php");
        var badge =
          l[1] === "Cart"
            ? '<span class="cart-badge" id="cartBadge">' +
              D.cartCount() +
              "</span>"
            : "";
        return (
          '<a href="' +
          l[0] +
          '"' +
          (on ? ' class="on"' : "") +
          ">" +
          l[1] +
          badge +
          "</a>"
        );
      })
      .join("") +
    (user
      ? '<a href="#" id="logoutLink">Log Out</a>'
      : '<a class="btn btn-red btn-sm" href="login.php">Log In</a>');

  var h = document.getElementById("siteHeader");
  if (h)
    h.innerHTML =
      '<div class="wrap hdr-in"><a class="brand" href="index.php"><img class="logo" src="assets/logonobg.png" alt="Densco Health Products logo"><span class="brand-t"><b>DENSCO</b><i>HEALTH PRODUCTS</i></span></a><button class="menu-btn" id="menuBtn">Menu</button><nav class="nav" id="nav">' +
      nav +
      "</nav></div>";

  var b = D.biz(),
    f = document.getElementById("siteFooter");
  if (f)
    f.innerHTML =
      '<div class="wrap"><div class="ftr-grid"><div><b>Densco Health Products</b><p>Manufacturer and distributor of certified hospital, clinic and home healthcare supplies.</p><p>' +
      D.esc(b.address) +
      "</p></div><div><b>Customer support</b><p>" +
      D.esc(b.hours) +
      "</p><p>" +
      D.esc(b.contact) +
      ' supported for inquiries</p></div><div><b>Account</b><p><a href="login.php">Log in</a></p><p><a href="register.php">Create an account</a></p><p><a href="staff/login.php">Staff portal</a></p></div></div><div class="ftr-bot"><span>\u00A9 2026 Densco Health Products. All rights reserved.</span></div></div>';

  D.updateBadge = function () {
    var x = document.getElementById("cartBadge");
    if (x) x.textContent = D.cartCount();
  };
  var mb = document.getElementById("menuBtn");
  if (mb)
    mb.addEventListener("click", function () {
      document.getElementById("nav").classList.toggle("open");
    });
  var lo = document.getElementById("logoutLink");
  if (lo)
    lo.addEventListener("click", function (e) {
      e.preventDefault();
      D.logout();
      location.href = "index.php";
    });

  function go() {
    var n = new URLSearchParams(location.search).get("next");
    location.href =
      n && /^[a-z-]+\.(html|php)(\?.*)?$/.test(n) ? n : "account.html";
  }

  var lf = document.getElementById("loginForm");
  if (lf)
    lf.addEventListener("submit", function (e) {
      e.preventDefault();
      var err = document.getElementById("formErr");
      var uVal = lf.elements.user.value.trim(),
        pVal = lf.elements.pw.value;
      if (!uVal || !pVal) {
        err.textContent = "Enter your email or username and your password.";
        return;
      }
      err.textContent = "";
      fetch("login.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ user: uVal, pw: pVal }),
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (data) {
          if (data.success) {
            D.login(data.name);
            go();
          } else {
            err.textContent = data.error || "Login failed. Please try again.";
          }
        })
        .catch(function () {
          err.textContent = "Something went wrong. Please try again.";
        });
    });

  var rf = document.getElementById("registerForm");
  if (rf)
    rf.addEventListener("submit", function (e) {
      e.preventDefault();
      var el = rf.elements,
        err = document.getElementById("formErr");
      var nameVal = el.name.value.trim(),
        emailVal = el.email.value.trim(),
        pwVal = el.pw.value,
        pw2Val = el.pw2.value;
      if (!nameVal || !emailVal || !pwVal) {
        err.textContent = "Fill in your name, email and a password.";
        return;
      }
      if (pwVal !== pw2Val) {
        err.textContent = "The two passwords don't match.";
        return;
      }
      err.textContent = "";
      fetch("register.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          name: nameVal,
          email: emailVal,
          pw: pwVal,
          pw2: pw2Val,
        }),
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (data) {
          if (data.success) {
            D.login(data.name);
            go();
          } else {
            err.textContent =
              data.error || "Registration failed. Please try again.";
          }
        })
        .catch(function () {
          err.textContent = "Something went wrong. Please try again.";
        });
    });
})();