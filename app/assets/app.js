(function () {
  "use strict";
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var tk = function (n) { return "৳" + Math.round(n || 0).toLocaleString("en-US"); };
  var esc = function (s) { return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; }); };

  // Mobile menu
  var side = $("#side"), scrim = $("#scrim"), mb = $("#menuBtn");
  function menu(open) {
    if (!side) return;
    side.classList.toggle("open", open);
    scrim.classList.toggle("open", open);
    mb.setAttribute("aria-expanded", open ? "true" : "false");
  }
  if (mb) mb.addEventListener("click", function () { menu(!side.classList.contains("open")); });
  if (scrim) scrim.addEventListener("click", function () { menu(false); });

  // Live customer search in the top bar
  var gs = $("#gsearch"), pop = $("#searchPop"), timer, active = -1;
  if (gs && pop) {
    gs.addEventListener("input", function () {
      clearTimeout(timer);
      var v = gs.value.trim();
      if (!v) { pop.hidden = true; return; }
      timer = setTimeout(function () {
        fetch("index.php?r=search&q=" + encodeURIComponent(v), { credentials: "same-origin" })
          .then(function (r) { return r.json(); })
          .then(function (rows) {
            active = -1;
            if (!rows.length) { pop.innerHTML = '<div class="empty">কোনো ক্রেতা পাওয়া যায়নি</div>'; pop.hidden = false; return; }
            pop.innerHTML = rows.map(function (c) {
              var av = c.photo ? '<img class="avatar" src="' + esc(c.photo) + '" alt="">' : '<span class="avatar">' + esc(c.initials) + "</span>";
              return '<a href="' + esc(c.url) + '">' + av + '<span><b>' + esc(c.name) + '</b><br><span class="meta">' + (c.memo ? "মেমো " + esc(c.memo) : "#" + c.id) + " · " + esc(c.phone || "ফোন নেই") + "</span></span>" +
                '<span class="amt ' + (c.overdue > 0 ? "t-red" : "") + '">' + (c.balance > 0 ? tk(c.balance) + " বাকি" : '<span class="t-green">বাকি নেই</span>') + "</span></a>";
            }).join("");
            pop.hidden = false;
          })
          .catch(function () { pop.hidden = true; });
      }, 180);
    });
    gs.addEventListener("keydown", function (e) {
      var links = $$("a", pop);
      if (pop.hidden || !links.length) return;
      if (e.key === "ArrowDown" || e.key === "ArrowUp") {
        e.preventDefault();
        active = (active + (e.key === "ArrowDown" ? 1 : -1) + links.length) % links.length;
        links.forEach(function (l, i) { l.classList.toggle("act", i === active); });
      } else if (e.key === "Enter" && active >= 0) {
        e.preventDefault(); location.href = links[active].href;
      } else if (e.key === "Escape") { pop.hidden = true; }
    });
    document.addEventListener("click", function (e) { if (!e.target.closest(".search")) pop.hidden = true; });
  }

  // Clickable table rows
  $$("tr[data-href]").forEach(function (tr) {
    tr.classList.add("click");
    tr.addEventListener("click", function (e) { if (!e.target.closest("a,button,form")) location.href = tr.dataset.href; });
  });

  // Confirm destructive actions
  $$("form[data-confirm]").forEach(function (f) {
    f.addEventListener("submit", function (e) { if (!confirm(f.dataset.confirm)) e.preventDefault(); });
  });

  // New-sale calculator: the shop's own price, down payment and installment.
  var sf = $("#saleForm");
  if (sf) {
    var prod = $("#product_id", sf), price = $("#price", sf), down = $("#down_payment", sf),
        months = $("#months", sf), inst = $("#installment", sf), cost = $("#cost_price", sf),
        pname = $("#product_name", sf), memo = $("#memo_no", sf), cust = $("#customer_id", sf);
    var n = function (el) { return parseFloat((el.value || "0").replace(/,/g, "")) || 0; };
    function recalc() {
      var p = n(price), d = n(down), m = Math.max(parseInt(months.value, 10) || 0, 0), c = n(cost);
      var total = Math.max(p - d, 0);
      var i = n(inst) > 0 ? Math.round(n(inst)) : (m ? Math.round(total / m) : total);
      var last = m ? total - i * (m - 1) : total;
      $("#c_price").textContent = tk(p);
      $("#c_down").textContent = "− " + tk(d);
      $("#c_total").textContent = tk(total);
      var same = m <= 1 || Math.abs(last - i) < 1;
      $("#c_inst").textContent = m ? tk(i) + " × " + (same ? m : m - 1) : "—";
      $("#c_last").textContent = same ? "—" : tk(last);
      if (!n(inst)) inst.placeholder = m ? "নিজে থেকে: " + tk(Math.round(total / m)) : "নিজে থেকে হিসাব হবে";
      var showProfit = c > 0 && p > 0;
      $("#c_profit").hidden = $("#c_profit_l").hidden = !showProfit;
      $("#c_profit").textContent = tk(p - c);
      var warn = $("#c_warn"), msg = "";
      if (d > p && p > 0) msg = "ডাউন পেমেন্ট বিক্রয়মূল্যের চেয়ে বেশি।";
      else if (total > 0 && m > 0 && last <= 0) msg = "মাসিক কিস্তি বেশি হয়ে গেছে। কিস্তির পরিমাণ বা সংখ্যা কমান।";
      warn.textContent = msg; warn.hidden = !msg;
    }
    if (prod) prod.addEventListener("change", function () {
      var o = prod.options[prod.selectedIndex];
      if (o && o.value) { pname.value = o.dataset.name; if (!n(cost)) cost.value = o.dataset.cost || ""; }
      recalc();
    });
    if (cust && memo) cust.addEventListener("change", function () {
      var o = cust.options[cust.selectedIndex];
      if (o && o.dataset.memo && !memo.value) memo.value = o.dataset.memo;
    });
    [price, down, months, inst, cost].forEach(function (el) { el.addEventListener("input", recalc); });
    recalc();
  }

  // Shrink photos in the browser before upload: max 1280px, under 400 KB.
  // Saves mobile data and avoids hosting upload limits; the server checks again.
  var MAX_BYTES = 400 * 1024, MAX_SIDE = 1280;
  function shrink(file) {
    return new Promise(function (resolve) {
      if (!/^image\//.test(file.type) || !window.createImageBitmap) return resolve(file);
      createImageBitmap(file, { imageOrientation: "from-image" }).then(function (bmp) {
        var side = MAX_SIDE, tries = 0;
        (function attempt() {
          var s = Math.min(1, side / Math.max(bmp.width, bmp.height));
          var cv = document.createElement("canvas");
          cv.width = Math.round(bmp.width * s); cv.height = Math.round(bmp.height * s);
          var ctx = cv.getContext("2d");
          ctx.fillStyle = "#fff"; ctx.fillRect(0, 0, cv.width, cv.height);
          ctx.drawImage(bmp, 0, 0, cv.width, cv.height);
          var qs = [0.8, 0.7, 0.6, 0.5], qi = 0;
          (function enc() {
            cv.toBlob(function (b) {
              if (b && b.size <= MAX_BYTES) return resolve(new File([b], file.name.replace(/\.\w+$/, "") + ".jpg", { type: "image/jpeg" }));
              if (++qi < qs.length) return enc();
              if (++tries < 6) { side = Math.round(side * 0.8); return attempt(); }
              resolve(file);
            }, "image/jpeg", qs[qi]);
          })();
        })();
      }).catch(function () { resolve(file); });
    });
  }
  $$('input[type=file][accept^="image"]').forEach(function (inp) {
    var note = document.createElement("p");
    note.className = "hint";
    inp.insertAdjacentElement("afterend", note);
    inp.addEventListener("change", function () {
      var f = inp.files && inp.files[0];
      if (!f) { note.textContent = ""; return; }
      note.textContent = "ছবি ছোট করা হচ্ছে…";
      shrink(f).then(function (out) {
        try { var dt = new DataTransfer(); dt.items.add(out); inp.files = dt.files; } catch (e) {}
        var kb = Math.round(out.size / 1024);
        note.textContent = (out.size <= MAX_BYTES ? "✓ প্রস্তুত: " : "") + kb + " KB" + (f.size > out.size ? " (আগে ছিল " + Math.round(f.size / 1024) + " KB)" : "");
      });
    });
  });
})();
