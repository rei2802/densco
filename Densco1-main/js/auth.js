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
      "</p>" +
      (b.viber ? "<p>Viber: " + D.esc(b.viber) + "</p>" : "") +
      (D.safeUrl(b.facebook)
        ? '<p><a href="' + D.esc(D.safeUrl(b.facebook)) + '" target="_blank" rel="noopener">Facebook page</a></p>'
        : "") +
      "<p>" +
      D.esc(b.contact) +
      ' supported for inquiries</p></div><div><b>Account</b><p><a href="login.php">Log in</a></p><p><a href="register.php">Create an account</a></p></div></div><div class="ftr-bot"><span>\u00A9 2026 Densco Health Products. All rights reserved.</span></div></div>';

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

  // ---------------------------------------------------------------------
  // Shared form helpers (login, register, forgot / reset password)
  // ---------------------------------------------------------------------
  var EYE =
    '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>';
  var EYE_OFF =
    '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.9 10.9 0 0 1 12 19c-7 0-11-7-11-7a19.8 19.8 0 0 1 5.06-5.94"/><path d="M9.9 4.24A10.9 10.9 0 0 1 12 4c7 0 11 7 11 7a19.7 19.7 0 0 1-3.17 4.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';

  // Wraps every password box in a field with a show / hide (eye) button.
  function addEyeToggles(root) {
    var boxes = root.querySelectorAll('input[type="password"]');
    Array.prototype.forEach.call(boxes, function (inp) {
      var wrap = document.createElement("div");
      wrap.className = "pw-wrap";
      inp.parentNode.insertBefore(wrap, inp);
      wrap.appendChild(inp);
      var btn = document.createElement("button");
      btn.type = "button";
      btn.className = "pw-toggle";
      btn.setAttribute("aria-label", "Show password");
      btn.setAttribute("aria-pressed", "false");
      btn.innerHTML = EYE;
      btn.addEventListener("click", function () {
        var show = inp.type === "password";
        inp.type = show ? "text" : "password";
        btn.innerHTML = show ? EYE_OFF : EYE;
        btn.setAttribute("aria-label", show ? "Hide password" : "Show password");
        btn.setAttribute("aria-pressed", show ? "true" : "false");
        inp.focus();
      });
      wrap.appendChild(btn);
    });
  }

  // Field-level errors: the message sits directly under its own text box.
  function errEl(form, name) {
    return form.querySelector("#err-" + name) || form.querySelector("#formErr");
  }
  function clearErrors(form) {
    Array.prototype.forEach.call(form.querySelectorAll(".field-err"), function (p) {
      p.textContent = "";
    });
    Array.prototype.forEach.call(form.querySelectorAll(".field.invalid"), function (f) {
      f.classList.remove("invalid");
    });
  }
  function setError(form, name, msg) {
    var p = errEl(form, name);
    p.textContent = msg;
    var f = p.closest(".field");
    if (f) f.classList.add("invalid");
  }
  function clearOnType(form) {
    form.addEventListener("input", function (e) {
      var f = e.target.closest && e.target.closest(".field");
      if (!f) return;
      var p = f.querySelector(".field-err");
      if (p) p.textContent = "";
      f.classList.remove("invalid");
    });
  }
  function focusFirstError(form) {
    var bad = form.querySelector(".field.invalid input");
    if (bad) bad.focus();
  }

  // Full-screen loading screen + disabled button while a request is running.
  function setLoading(on, btn, text) {
    var ov = document.getElementById("loadingOverlay");
    if (on) {
      if (!ov) {
        ov = document.createElement("div");
        ov.id = "loadingOverlay";
        ov.className = "loading-overlay";
        ov.setAttribute("role", "status");
        ov.innerHTML = '<div class="loading-box"><span class="spinner"></span><p></p></div>';
        document.body.appendChild(ov);
      }
      ov.querySelector("p").textContent = text || "Please wait...";
    } else if (ov) {
      ov.remove();
    }
    if (btn) {
      if (on) {
        btn.dataset.label = btn.textContent;
        btn.textContent = text || "Please wait...";
      } else if (btn.dataset.label) {
        btn.textContent = btn.dataset.label;
      }
      btn.disabled = on;
    }
  }

  // POSTs JSON and always resolves to an object {success, error, field, ...}.
  // Network failures, bad server replies and server errors are caught here and
  // turned into a plain-language message instead of a silent failure.
  function postJSON(url, payload) {
    return fetch(url, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    })
      .then(function (r) {
        return r.text().then(function (t) {
          var data = null;
          try {
            data = JSON.parse(t);
          } catch (e) {}
          if (!data || typeof data !== "object") {
            return {
              success: false,
              field: "form",
              error: "The server sent an unexpected reply (error " + r.status + "). Please try again in a moment.",
            };
          }
          return data;
        });
      })
      .catch(function () {
        return {
          success: false,
          field: "form",
          error: "We can't reach the server. Check your internet connection and try again.",
        };
      });
  }

  var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
  function emailProblem(v) {
    if (!v) return "Enter your email address.";
    if (v.length > 100) return "Email is too long (100 characters at most).";
    if (!EMAIL_RE.test(v)) return "Enter a valid email address, for example name@example.com.";
    return "";
  }

  D.form = {
    addEyeToggles: addEyeToggles,
    clearErrors: clearErrors,
    setError: setError,
    clearOnType: clearOnType,
    focusFirstError: focusFirstError,
    setLoading: setLoading,
    postJSON: postJSON,
    emailProblem: emailProblem,
  };

  [].forEach.call(
    document.querySelectorAll("#loginForm, #registerForm, #forgotForm, #resetForm"),
    function (f) {
      addEyeToggles(f);
      clearOnType(f);
    }
  );

  function go() {
    var n = new URLSearchParams(location.search).get("next");
    location.href =
      n && /^[a-z-]+\.(html|php)(\?.*)?$/.test(n) ? n : "account.html";
  }

  // Shows an error returned by the server under the right field.
  function showServerError(form, data) {
    var field = data.field || "form";
    if (!form.querySelector("#err-" + field)) field = "form";
    setError(form, field, data.error || "Something went wrong. Please try again.");
    focusFirstError(form);
  }

  var lf = document.getElementById("loginForm");
  if (lf)
    lf.addEventListener("submit", function (e) {
      e.preventDefault();
      clearErrors(lf);
      var btn = document.getElementById("submitBtn");
      var uVal = lf.elements.user.value.trim(),
        pVal = lf.elements.pw.value;
      var bad = false;
      if (!uVal) {
        setError(lf, "user", "Enter your email or username.");
        bad = true;
      }
      if (!pVal) {
        setError(lf, "pw", "Enter your password.");
        bad = true;
      }
      if (bad) return focusFirstError(lf);

      setLoading(true, btn, "Logging in...");
      postJSON("login.php", { user: uVal, pw: pVal }).then(function (data) {
        if (data.success && data.staff) {
          // Staff account: same form, but go to the staff dashboard
          D.put("denscoStaffRole", data.role);
          location.href = "staff/dashboard.html";
          return;
        }
        if (data.success) {
          D.login(data.name);
          go();
          return;
        }
        setLoading(false, btn);
        showServerError(lf, data);
      });
    });

  var rf = document.getElementById("registerForm");
  if (rf) {
    var emailBox = rf.elements.email;
    emailBox.addEventListener("blur", function () {
      var v = emailBox.value.trim();
      if (v) {
        var msg = emailProblem(v);
        if (msg) setError(rf, "email", msg);
      }
    });
    rf.elements.pw2.addEventListener("blur", function () {
      if (rf.elements.pw2.value && rf.elements.pw.value !== rf.elements.pw2.value)
        setError(rf, "pw2", "The two passwords don't match.");
    });

    rf.addEventListener("submit", function (e) {
      e.preventDefault();
      clearErrors(rf);
      var btn = document.getElementById("submitBtn");
      var el = rf.elements;
      var nameVal = el.name.value.trim(),
        emailVal = el.email.value.trim(),
        pwVal = el.pw.value,
        pw2Val = el.pw2.value;
      var bad = false;
      function fail(f, m) {
        setError(rf, f, m);
        bad = true;
      }
      if (!nameVal) fail("name", "Enter your full name.");
      else if (nameVal.length > 100) fail("name", "Name is too long (100 characters at most).");
      var em = emailProblem(emailVal);
      if (em) fail("email", em);
      if (!pwVal) fail("pw", "Enter a password.");
      else if (pwVal.length < 6) fail("pw", "Password must be at least 6 characters.");
      else if (pwVal.length > 72) fail("pw", "Password must be 72 characters or fewer.");
      if (!pw2Val) fail("pw2", "Confirm your password by typing it again.");
      else if (pwVal && pwVal !== pw2Val) fail("pw2", "The two passwords don't match.");
      if (bad) return focusFirstError(rf);

      // The confirm-password value is checked here and is not sent to the server.
      setLoading(true, btn, "Creating your account...");
      postJSON("register.php", { name: nameVal, email: emailVal, pw: pwVal }).then(function (data) {
        if (data.success) {
          D.login(data.name);
          go();
          return;
        }
        setLoading(false, btn);
        showServerError(rf, data);
      });
    });
  }
})();