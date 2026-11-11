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
              return '<a href="' + esc(c.url) + '">' + av + '<span><b>' + esc(c.name) + '</b><br><span class="meta">#' + c.id + " · " + esc(c.phone || "ফোন নেই") + "</span></span>" +
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

  // New-sale calculator
  var sf = $("#saleForm");
  if (sf) {
    var prod = $("#product_id", sf), price = $("#price", sf), down = $("#down_payment", sf),
        rate = $("#profit_rate", sf), months = $("#months", sf), pname = $("#product_name", sf);
    function n(el) { return parseFloat((el.value || "0").replace(/,/g, "")) || 0; }
    function recalc() {
      var p = n(price), d = n(down), r = n(rate), m = Math.max(parseInt(months.value, 10) || 0, 0);
      var fin = Math.max(p - d, 0), profit = Math.round(fin * r / 100 * m), total = fin + profit;
      var inst = m ? Math.round(total / m) : total;
      $("#c_fin").textContent = tk(fin);
      $("#c_profit").textContent = tk(profit);
      $("#c_total").textContent = tk(total);
      $("#c_inst").textContent = m ? tk(inst) + " × " + m + " মাস" : "—";
    }
    if (prod) prod.addEventListener("change", function () {
      var o = prod.options[prod.selectedIndex];
      if (o && o.dataset.price) { price.value = o.dataset.price; }
      if (o && o.value) { pname.value = o.dataset.name; }
      recalc();
    });
    [price, down, rate, months].forEach(function (el) { el.addEventListener("input", recalc); });
    recalc();
  }
})();
