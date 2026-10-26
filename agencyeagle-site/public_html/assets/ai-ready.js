// AI-Ready Site Score: gate form -> questions -> scoring -> report. Plain JS, no libraries.
// Every piece of text from the server (including text read off the visitor's site) goes in with textContent.
(function () {
  'use strict';

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }
  function post(url, body) {
    return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body), credentials: 'same-origin' })
      .then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); });
  }
  function beacon(e) {
    try { navigator.sendBeacon('/ai-ready/beacon.php', JSON.stringify({ e: e })); } catch (err) { /* counts are best effort */ }
  }

  // ---------- Quiz page ----------
  var gate = document.getElementById('quiz-gate');
  if (gate) {
    beacon('view');
    var card = document.getElementById('quiz');
    var utm = {};
    new URLSearchParams(location.search).forEach(function (v, k) { if (k.indexOf('utm_') === 0) utm[k.slice(4)] = v; });

    gate.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var btn = gate.querySelector('button[type=submit]');
      ['form', 'site', 'name', 'email'].forEach(function (k) { document.getElementById('err-' + k).hidden = true; });
      var data = { utm: utm, ref: document.referrer };
      ['site', 'name', 'email', 'business', 'phone', 'company_url'].forEach(function (k) { data[k] = gate.elements[k].value; });
      btn.disabled = true;
      btn.textContent = 'Starting…';
      post('/ai-ready/start.php', data).then(function (res) {
        if (res.body.errors) {
          Object.keys(res.body.errors).forEach(function (k) {
            var e = document.getElementById('err-' + k) || document.getElementById('err-form');
            e.textContent = res.body.errors[k];
            e.hidden = false;
          });
          btn.disabled = false;
          btn.textContent = 'Start my score';
          return;
        }
        // The live scan runs while they answer; finish.php waits for it if it isn't done.
        fetch('/ai-ready/scan.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ token: res.body.token }), keepalive: true }).catch(function () {});
        runQuestions(res.body.token, res.body.site, res.body.questions);
      }).catch(function () {
        var e = document.getElementById('err-form');
        e.textContent = 'Something went wrong. Please try again, or call (865) 300-5397.';
        e.hidden = false;
        btn.disabled = false;
        btn.textContent = 'Start my score';
      });
    });

    function runQuestions(token, site, questions) {
      var answers = {};
      var i = 0;
      function show() {
        card.textContent = '';
        var q = questions[i];
        var wrap = el('div', 'quiz-step');
        var prog = el('div', 'quiz-progress');
        var bar = el('span');
        bar.style.width = Math.round((i / questions.length) * 100) + '%';
        prog.appendChild(bar);
        wrap.appendChild(prog);
        wrap.appendChild(el('p', 'quiz-count', 'Question ' + (i + 1) + ' of ' + questions.length + ' · checking ' + site.replace(/^https:\/\/|\/$/g, '') + ' in the background'));
        var fs = el('fieldset', 'quiz-q');
        fs.appendChild(el('legend', null, q.q));
        q.options.forEach(function (o) {
          var b = el('button', 'quiz-option', o.label);
          b.type = 'button';
          b.addEventListener('click', function () {
            answers[q.id] = o.value;
            i++;
            if (i < questions.length) show(); else finish();
          });
          fs.appendChild(b);
        });
        wrap.appendChild(fs);
        if (q.why) wrap.appendChild(el('p', 'quiz-why', 'Why it matters: ' + q.why));
        if (i > 0) {
          var back = el('button', 'quiz-back', '← Back');
          back.type = 'button';
          back.addEventListener('click', function () { i--; show(); });
          wrap.appendChild(back);
        }
        card.appendChild(wrap);
        var first = fs.querySelector('button');
        if (first) first.focus();
      }
      function finish() {
        card.textContent = '';
        var w = el('div', 'quiz-step quiz-working');
        w.appendChild(el('h2', null, 'Scoring your site…'));
        var steps = ['Reading robots.txt', 'Checking Googlebot and Bingbot access', 'Checking ChatGPT, Claude, Perplexity and Gemini access', 'Looking for your sitemap', 'Reading your structured data', 'Looking for llms.txt', 'Adding up your score'];
        var list = el('ul', 'checks working-list');
        w.appendChild(list);
        card.appendChild(w);
        var s = 0;
        var timer = setInterval(function () { if (s < steps.length) list.appendChild(el('li', null, steps[s++])); }, 900);
        post('/ai-ready/finish.php', { token: token, answers: answers }).then(function (res) {
          clearInterval(timer);
          if (res.body.report_url) { location.href = res.body.report_url; return; }
          card.textContent = '';
          card.appendChild(el('p', 'form-error', res.body.error || 'Something went wrong. Please call (865) 300-5397.'));
        }).catch(function () {
          clearInterval(timer);
          card.textContent = '';
          card.appendChild(el('p', 'form-error', 'We lost the connection while scoring. Check your email in a minute, or call (865) 300-5397.'));
        });
      }
      show();
    }
  }

  // ---------- Report page ----------
  var root = document.getElementById('report');
  if (root) {
    var token = new URLSearchParams(location.search).get('t') || '';
    fetch('/ai-ready/result.php?t=' + encodeURIComponent(token)).then(function (r) { return r.json(); }).then(function (j) {
      if (!j.report) {
        document.getElementById('report-sub').textContent = j.error || "We couldn't load that report.";
        return;
      }
      render(j.report);
    }).catch(function () {
      document.getElementById('report-sub').textContent = "We couldn't load that report. Please refresh the page.";
    });
  }

  function mark(pass) { return pass === true ? '✓' : pass === 'partial' ? '!' : '✗'; }
  function markCls(pass) { return pass === true ? 'ok' : pass === 'partial' ? 'part' : 'bad'; }
  function cta(a) { a.addEventListener('click', function () { beacon('cta'); }); return a; }
  function link(href, cls, text) { var a = el('a', cls, text); a.href = href; return cta(a); }

  function render(r) {
    var host = r.site.replace(/^https:\/\/|\/$/g, '');
    document.getElementById('report-title').textContent = 'AI-Ready Site Score for ' + host;
    document.getElementById('report-sub').textContent = 'We emailed a copy of this report to you. Bookmark this page to come back to it.';
    root.textContent = '';

    var top = el('div', 'score-top');
    var ring = el('div', 'score-ring grade-' + r.grade.letter.toLowerCase());
    ring.style.setProperty('--pct', r.score);
    ring.appendChild(el('span', 'score-num', String(r.score)));
    ring.appendChild(el('span', 'score-of', 'out of 100'));
    top.appendChild(ring);
    var sum = el('div', 'score-summary');
    sum.appendChild(el('p', 'score-grade', 'Grade ' + r.grade.letter + ': ' + r.grade.label));
    sum.appendChild(el('p', null, 'Live site check: ' + r.scan_points + ' of 70 points. Your answers: ' + r.quiz_points + ' of 30 points.'));
    if (!r.reached) sum.appendChild(el('p', 'form-error', "We couldn't load your site (" + r.scan_error + '). If our checker can\'t reach it, search and AI crawlers may not either.'));
    if (r.wordpress) sum.appendChild(el('p', 'note', 'Your site runs on WordPress. That works for search, but it needs constant plugin updates and is the most attacked platform on the web.'));
    top.appendChild(sum);
    root.appendChild(top);

    if (r.fixes.length) {
      var fx = el('section', 'fixes');
      fx.appendChild(el('h2', null, 'Your top ' + r.fixes.length + ' fixes'));
      var ol = el('ol', 'fix-list');
      r.fixes.forEach(function (f) {
        var li = el('li', 'card');
        li.appendChild(el('h3', null, f.fix));
        li.appendChild(el('p', null, f.detail));
        var p = el('p', 'fix-plan');
        p.appendChild(document.createTextNode('Included in: '));
        p.appendChild(link(f.url, null, f.plan));
        li.appendChild(p);
        ol.appendChild(li);
      });
      fx.appendChild(ol);
      if (r.all_fixes > r.fixes.length) fx.appendChild(el('p', 'note', (r.all_fixes - r.fixes.length) + ' smaller items are listed in the full breakdown below.'));
      root.appendChild(fx);
    }

    var offer = el('section', 'offer-card report-offer');
    offer.appendChild(el('p', 'offer-tag', 'What we recommend'));
    offer.appendChild(el('h2', null, r.offer.plan));
    offer.appendChild(el('p', null, r.offer.pitch));
    var acts = el('p', 'hero-actions');
    acts.appendChild(link('/contact/?from=ai-ready', 'button cta-button big', 'Book a free walkthrough'));
    acts.appendChild(link('/pricing/', 'button ghost big', 'See plans and pricing'));
    offer.appendChild(acts);
    offer.appendChild(link('tel:+18653005397', 'call-link', 'Or call (865) 300-5397'));
    root.appendChild(offer);

    var br = el('section', 'breakdown');
    br.appendChild(el('h2', null, 'Full breakdown'));
    r.groups.forEach(function (g) {
      var d = el('details', 'card group');
      var s = el('summary');
      s.appendChild(el('span', 'group-name', g.label));
      s.appendChild(el('span', 'group-pts', g.points + ' / ' + g.max));
      d.appendChild(s);
      var ul = el('ul', 'check-list');
      g.checks.forEach(function (c) {
        var li = el('li', 'check ' + markCls(c.pass));
        li.appendChild(el('span', 'check-mark', mark(c.pass)));
        var body = el('div');
        body.appendChild(el('strong', null, c.label + ' (' + c.points + '/' + c.max + ')'));
        body.appendChild(el('p', null, c.detail));
        li.appendChild(body);
        ul.appendChild(li);
      });
      d.appendChild(ul);
      br.appendChild(d);
    });
    root.appendChild(br);
  }
})();
