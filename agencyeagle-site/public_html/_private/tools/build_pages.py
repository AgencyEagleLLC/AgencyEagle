import json, os, sys
ROOT = sys.argv[1]
BASE = "https://agencyeagle.com"
PHONE = "(865) 300-5397"; TEL = "+18653005397"

ORG = {
  "@context": "https://schema.org", "@type": "ProfessionalService",
  "name": "Agency Eagle", "url": BASE + "/", "telephone": TEL,
  "email": "sales@agencyeagle.com", "foundingDate": "2002",
  "description": "Custom website development and hosting from East Tennessee.",
  "address": {"@type": "PostalAddress", "postOfficeBoxNumber": "99", "addressLocality": "Jefferson City",
              "addressRegion": "TN", "postalCode": "37760", "addressCountry": "US"},
  "areaServed": "United States",
}

NAV = [("/services/", "Services"), ("/pricing/", "Pricing"), ("/ai-ready/", "Free AI Score"), ("/contact/", "Contact")]

def page(path, title, desc, body, current=None, extra_ld=None, noindex=False, script=None):
    url = BASE + path
    nav = "\n".join(
        f'        <li><a href="{h}"{" aria-current=\"page\"" if h == current else ""}>{t}</a></li>' for h, t in NAV)
    lds = [ORG] + ([extra_ld] if extra_ld else [])
    ld = "\n".join(f'<script type="application/ld+json">\n{json.dumps(x, indent=2)}\n</script>' for x in lds)
    html = f'''<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{title}</title>
<meta name="description" content="{desc}">
{'<meta name="robots" content="noindex">' if noindex else ''}
<link rel="canonical" href="{url}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Agency Eagle">
<meta property="og:title" content="{title}">
<meta property="og:description" content="{desc}">
<meta property="og:url" content="{url}">
<link rel="icon" href="/favicon.ico">
<link rel="stylesheet" href="/assets/site.css">
{f'<script src="{script}" defer></script>' if script else ''}
{ld}
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<div class="topbar">
  <div class="container">
    <span>Websites and hosting from East Tennessee since 2002</span>
    <span><a href="mailto:help@agencyeagle.com">Support</a> <a href="mailto:accounting@agencyeagle.com">Billing</a></span>
  </div>
</div>
<header class="site-header">
  <div class="container">
    <a class="logo" href="/"><img src="/assets/agency-eagle-logo.png" alt="Agency Eagle" width="354" height="40"></a>
    <nav class="site-nav" aria-label="Main">
      <ul>
{nav}
      </ul>
    </nav>
    <a class="header-call" href="tel:{TEL}">Call {PHONE}</a>
  </div>
</header>

<main id="main">
{body}
</main>

<footer class="site-footer">
  <div class="container footer-grid">
    <div>
      <p><img src="/assets/agency-eagle-logo-footer.png" alt="Agency Eagle" width="197" height="20"></p>
      <p>Custom websites and hosting from East Tennessee, since 2002.</p>
    </div>
    <nav aria-label="Footer">
      <p class="footer-head">Pages</p>
      <ul>
        <li><a href="/">Home</a></li>
        <li><a href="/services/">Services</a></li>
        <li><a href="/pricing/">Pricing</a></li>
        <li><a href="/ai-ready/">Free AI-Ready Site Score</a></li>
        <li><a href="/contact/">Contact</a></li>
      </ul>
    </nav>
    <div>
      <p class="footer-head">Get in touch</p>
      <p><a href="tel:{TEL}">{PHONE}</a><br>
      Sales: <a href="mailto:sales@agencyeagle.com">sales@agencyeagle.com</a><br>
      Service: <a href="mailto:help@agencyeagle.com">help@agencyeagle.com</a><br>
      P.O. Box 99, Jefferson City, TN 37760</p>
    </div>
  </div>
  <div class="container"><p class="copyright">&copy; 2026 Agency Eagle</p></div>
</footer>
</body>
</html>
'''
    out = os.path.join(ROOT, path.strip("/"), "index.html") if path != "/404.html" else os.path.join(ROOT, "404.html")
    if path == "/": out = os.path.join(ROOT, "index.html")
    os.makedirs(os.path.dirname(out), exist_ok=True)
    open(out, "w").write(html)

CTA = '''  <section class="cta">
    <div class="container">
      <h2>Let's build your site</h2>
      <p>Tell us about your business. We'll reply within one business day.</p>
      <a class="button cta-button big" href="/contact/">Request a consultation</a>
    </div>
  </section>'''

page("/", "The Anti-WordPress Website Company | Agency Eagle",
 "No WordPress, no plugins, no patching. Agency Eagle builds fast, secure HTML websites your AI can update safely. Hosting from $99/month.",
f'''  <section class="hero">
    <div class="container hero-grid">
      <div>
        <p class="eyebrow">The anti-WordPress website</p>
        <h1>All the speed and safety. None of the WordPress.</h1>
        <p class="lede">No plugins to patch, no theme to break, no login page for hackers to hammer. Just fast, clean websites that your AI can build and update in minutes, checked by our platform before anything goes live.</p>
        <p class="hero-actions"><a class="button big" href="/pricing/">See plans and pricing</a> <a class="button ghost big" href="/contact/">Request a consultation</a></p>
        <p class="hero-note">30-day money-back guarantee on every website setup.</p>
      </div>
      <aside class="offer-card" aria-label="Featured plan">
        <p class="offer-tag">Most popular</p>
        <h2>Hosting and client portal</h2>
        <p class="offer-price"><span class="from">Only</span> $99<span class="per">/mo</span></p>
        <ul class="checks">
          <li>Hosting, backups and client portal</li>
          <li>AI Brain for stronger organic SEO</li>
          <li>Social ad management tools</li>
          <li>Hashtag and hook social post creation</li>
          <li>Site management and monitoring</li>
        </ul>
        <a class="button cta-button" href="/contact/">Get started</a>
      </aside>
    </div>
  </section>

  <section class="score-band">
    <div class="container score-band-inner">
      <div>
        <p class="eyebrow">Free, two minutes</p>
        <h2>Can ChatGPT, Copilot and Google's AI find your business?</h2>
        <p>Get your AI-Ready Site Score. We check your site live for the things AI assistants look for, then show you exactly what to fix.</p>
      </div>
      <a class="button cta-button big" href="/ai-ready/">Get my free score</a>
    </div>
  </section>

  <section class="products">
    <div class="container">
      <h2 class="section-title">Everything you need to get online</h2>
      <div class="cards product-cards">
        <article class="card product">
          <h3>Custom website build</h3>
          <p>We design and build your site for you, mobile and search friendly, ready to launch.</p>
          <p class="starting">One time <strong>$5,000</strong></p>
          <a class="button" href="/contact/">Start my site</a>
        </article>
        <article class="card product">
          <h3>Growth plan</h3>
          <p>Everything in the $99 plan, plus funnel management and tracking, Google Analytics, Meta and Google pixel connections, and full access for your AI.</p>
          <p class="starting">Only <strong>$200/mo</strong></p>
          <a class="button" href="/pricing/">Compare plans</a>
        </article>
        <article class="card product">
          <h3>AI Brain for SEO</h3>
          <p>Fill out a simple brain about your business. It shapes every page for stronger organic search, and guides your own AI if you build with one.</p>
          <p class="starting">Included with <strong>$99/mo</strong></p>
          <a class="button" href="/services/#brain">How it works</a>
        </article>
        <article class="card product">
          <h3>Social media tools</h3>
          <p>Social ad management tools plus hashtag and hook post creation, so your posts get seen.</p>
          <p class="starting">Included with <strong>$99/mo</strong></p>
          <a class="button" href="/services/#social">Learn more</a>
        </article>
      </div>
    </div>
  </section>

  <section class="band compare-section">
    <div class="container">
      <h2 class="section-title">Why we left WordPress behind</h2>
      <div class="table-wrap">
        <table class="compare">
          <thead>
            <tr><th scope="col"></th><th scope="col">Typical WordPress site</th><th scope="col">Agency Eagle site</th></tr>
          </thead>
          <tbody>
            <tr><th scope="row">Security</th><td>Plugins, themes and an admin login that all need constant patching</td><td>Plain HTML files. No database, no plugins, nothing to exploit</td></tr>
            <tr><th scope="row">Speed</th><td>Pages assembled by PHP and a database on every visit</td><td>Pages served ready-made, so they load fast</td></tr>
            <tr><th scope="row">Upkeep</th><td>Core, theme and plugin updates that can break the site</td><td>Nothing to update. It just keeps working</td></tr>
            <tr><th scope="row">Making changes</th><td>Page builders and editors to learn</td><td>Ask your AI. Our platform checks every change before it goes live</td></tr>
            <tr><th scope="row">SEO</th><td>Depends on which plugins you add and how they are set up</td><td>Built in: titles, structured data and site maps on every page</td></tr>
            <tr><th scope="row">Backups</th><td>Another plugin to install and trust</td><td>Automatic before every change, with one-click restore</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <section class="trust">
    <div class="container trust-grid">
      <div><p class="trust-big">Since 2002</p><p>Building and hosting websites</p></div>
      <div><p class="trust-big">Hundreds</p><p>of businesses helped across the US and Canada</p></div>
      <div><p class="trust-big">30 days</p><p>money-back guarantee on every setup</p></div>
      <div><p class="trust-big">Real people</p><p>A small team that answers the phone</p></div>
    </div>
  </section>

  <section>
    <div class="container split">
      <div>
        <h2>Small on purpose</h2>
        <p>We intend to stay small, with a trusted team of designers and developers behind us, so you always talk to the people doing the work.</p>
        <a href="/contact/">Meet the team</a>
      </div>
      <ul class="checks">
        <li>Mobile friendly on every device</li>
        <li>Search engine friendly from day one</li>
        <li>No WordPress: fast, secure, nothing to patch</li>
        <li>Friendly support when you need a hand</li>
      </ul>
    </div>
  </section>

{CTA}''')

SERVICES = [
 ("design", "Professional, unique design", "If two contractors showed up to make repairs, you'd hire the one who arrived on time in a pressed uniform. Your website is that first impression. A professional site is more than pretty colors: it's built around your visitors and growing your business."),
 ("html", "No WordPress, on purpose", "We are the anti-WordPress web company. Your site is clean HTML and CSS: no plugins, no database, no admin login for hackers to target. It loads fast, there is nothing to patch, and your AI can build new pages in minutes, with every change checked by our platform first."),
 ("mobile", "Mobile friendly", "Most visitors arrive on a phone. A mobile-friendly site lets customers tap to call, get directions to your office, and see what you offer in seconds."),
 ("seo", "Search engine friendly", "People who find you through search are some of your best leads. Every page we build has the titles, descriptions, structured data and site maps search engines look for."),
 ("leads", "Lead generation", "Put your website to work. Clear calls to action and simple contact forms turn visitors into leads that belong to you alone, not shared with five competitors."),
 ("email", "Email marketing", "Stay in touch with clients and stay top of mind. Automated email can drive referrals, ask for reviews, and share timely news at low cost."),
 ("hosting", "Hosting, backups and portal", "We host your site, back it up before every change, and give you a secure portal to download a copy of your site, reset your hosting password, and publish updates."),
 ("brain", "AI Brain for organic SEO", "Fill out a short, simple brain about your business: who you serve, what you offer and where. Our AI Brain uses it to shape your titles, descriptions, page structure and content for stronger organic search rankings. Included with the $99 monthly plan."),
 ("social", "Social ad tools and post creation", "Get access to social ad management tools, plus social posts built with the hashtags and hooks that get attention. Included with the $99 monthly plan."),
 ("monitoring", "Management and monitoring", "We keep watch over your site: uptime, backups and search health, with fixes handled for you. Included with the $99 monthly plan."),
 ("growth", "Funnels, tracking and full AI access", "On the $200 Growth plan we manage and track your sales funnels and connect Google Analytics, the Meta Pixel and the Google tag. Your own AI gets full access to manage and work your site, with every change filtered through our platform tools so your SEO stays consistent."),
 ("ai", "Build it with your AI", "If you're building your site with an AI assistant, download the best-practices file written for your website, give it to your AI, then upload the result through your portal. We check every upload against our standards and keep the previous version one click away."),
 ("support", "Support and consulting", "We're friendly and want you to succeed. Make updates yourself, or send them to us. One-on-one training and consulting are available too."),
]
svc_html = "\n".join(f'''      <article class="service" id="{i}">
        <h2>{t}</h2>
        <p>{d}</p>
      </article>''' for i, t, d in SERVICES)
page("/services/", "Website Design, Hosting and SEO Services | Agency Eagle",
 "Custom website design, hosting with backups, SEO, lead generation and email marketing from Agency Eagle in East Tennessee.",
f'''  <section class="page-head">
    <div class="container">
      <h1>Services</h1>
      <p>Everything your business needs to look professional online and be found.</p>
    </div>
  </section>
  <section>
    <div class="container services">
{svc_html}
    </div>
  </section>
{CTA}''', current="/services/")

FAQ = {"@context": "https://schema.org", "@type": "FAQPage", "mainEntity": [
  {"@type": "Question", "name": "What if I don't like the site? Is there a guarantee?",
   "acceptedAnswer": {"@type": "Answer", "text": "Yes. We offer a 30-day, 100% money-back guarantee on any website setup."}},
  {"@type": "Question", "name": "Can I change plans at any time?",
   "acceptedAnswer": {"@type": "Answer", "text": "Yes. You can upgrade, downgrade or change plans at any time."}},
  {"@type": "Question", "name": "Is hosting included?",
   "acceptedAnswer": {"@type": "Answer", "text": "Yes. Both monthly plans include hosting. The $99 plan adds backups, your client portal, the AI Brain for SEO, social media tools, and site management and monitoring. The $200 Growth plan adds funnel management and tracking, analytics and pixel connections, and full AI access."}}]}
page("/pricing/", "Website Pricing and Hosting Plans | Agency Eagle",
 "Hosting plans from $99 a month with AI SEO and social tools, a $200 Growth plan with funnels and tracking, and $5,000 website builds.",
'''  <section class="page-head">
    <div class="container">
      <h1>Pricing</h1>
      <p class="slogan">Don't let a cheap price hold your company back from having a successful web presence and lead generation system.</p>
      <p>Simple plans. Hosting included. No surprises.</p>
    </div>
  </section>
  <section>
    <div class="container">
      <div class="cards">
        <article class="card plan">
          <h2>Hosting and portal</h2>
          <p class="price">$99<span>/month</span></p>
          <p>Everything a small business needs to look good and be found.</p>
          <ul class="checks">
            <li>Fast, secure hosting</li>
            <li>AI Brain for stronger organic SEO</li>
            <li>Social ad management tools</li>
            <li>Hashtag and hook social post creation</li>
            <li>Site management and monitoring</li>
            <li>Backup before every change, one-click restore</li>
            <li>Your own client portal</li>
            <li>Best-practices file for your AI, written for your site</li>
            <li>Standards check on every upload</li>
          </ul>
          <a class="button" href="/contact/">Get started</a>
        </article>
        <article class="card plan featured">
          <p class="offer-tag">Best for growth</p>
          <h2>Growth</h2>
          <p class="price">$200<span>/month</span></p>
          <p>Everything in Hosting and portal, plus:</p>
          <ul class="checks">
            <li>Funnel management and tracking</li>
            <li>Google Analytics connection</li>
            <li>Meta Pixel and Google tag (pixel) setup</li>
            <li>Full access for your AI to manage and work the site</li>
            <li>Every AI change runs through our platform tools, so your SEO stays consistent</li>
          </ul>
          <a class="button cta-button" href="/contact/">Get started</a>
        </article>
        <article class="card plan">
          <h2>Done-for-you build</h2>
          <p class="price">$5,000<span> one time</span></p>
          <ul class="checks">
            <li>Custom design for your business</li>
            <li>Mobile and search engine friendly</li>
            <li>Contact form and lead capture</li>
            <li>Launched on your Agency Eagle hosting</li>
          </ul>
          <a class="button" href="/contact/">Request a consultation</a>
        </article>
      </div>
      <p class="note">Need something bigger, like online ordering or a custom application? <a href="/contact/">Ask us for a quote</a>.</p>
    </div>
  </section>
  <section class="band">
    <div class="container faq">
      <h2>Questions</h2>
      <h3>What if I don't like the site? Is there a guarantee?</h3>
      <p>Yes. We offer a 30-day, 100% money-back guarantee on any website setup.</p>
      <h3>Can I change plans at any time?</h3>
      <p>Yes. You can upgrade, downgrade or change plans at any time.</p>
      <h3>Is hosting included?</h3>
      <p>Yes. Both monthly plans include hosting. The $99 plan adds backups, your client portal, the AI Brain for SEO, social media tools, and site management and monitoring. The $200 Growth plan adds funnel management and tracking, analytics and pixel connections, and full AI access.</p>
    </div>
  </section>''', current="/pricing/", extra_ld=FAQ)

page("/contact/", "Contact Agency Eagle | Website Design, Jefferson City TN",
 "Contact Agency Eagle for website design and hosting. Call (865) 300-5397 or email sales@agencyeagle.com.",
f'''  <section class="page-head">
    <div class="container">
      <h1>Contact us</h1>
      <p>Tell us about your business and what you need. We'll reply within one business day.</p>
    </div>
  </section>
  <section>
    <div class="container split">
      <form class="contact-form" method="post" action="/contact/send.php">
        <label for="name">Name</label>
        <input id="name" name="name" autocomplete="name" required>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="email" required>
        <label for="phone">Phone (optional)</label>
        <input id="phone" name="phone" type="tel" autocomplete="tel">
        <label for="message">How can we help?</label>
        <textarea id="message" name="message" rows="6" required></textarea>
        <div class="hp" aria-hidden="true"><label for="website">Leave this empty</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
        <button class="button" type="submit">Send message</button>
      </form>
      <aside class="contact-box">
        <h2>Agency Eagle</h2>
        <p><a href="tel:{TEL}">{PHONE}</a></p>
        <p>Sales<br><a href="mailto:sales@agencyeagle.com">sales@agencyeagle.com</a></p>
        <p>Customer service<br><a href="mailto:help@agencyeagle.com">help@agencyeagle.com</a></p>
        <p>Billing<br><a href="mailto:accounting@agencyeagle.com">accounting@agencyeagle.com</a><br>P.O. Box 99, Jefferson City, TN 37760</p>
      </aside>
    </div>
  </section>
  <section class="band">
    <div class="container">
      <h2>Meet the team</h2>
      <div class="cards team">
        <article class="card"><h3>Kevin Davenport</h3><p>Partner</p></article>
        <article class="card"><h3>Shawn Wilmoth</h3><p>Partner</p></article>
        <article class="card"><h3>Priscilla Wilmoth</h3><p>Accounting Manager<br><a href="mailto:cill@agencyeagle.com">cill@agencyeagle.com</a></p></article>
      </div>
    </div>
  </section>
  <section>
    <div class="container prose">
      <h2>Our story</h2>
      <p>Agency Eagle has helped hundreds of businesses across the United States and Canada with sales, service and marketing since 2002. We started as Laguna Internet Technologies in the rolling hills of East Tennessee and became Agency Eagle in January 2014.</p>
      <p>Today we focus on custom website design and hosting. We intend to stay small. Behind the curtain is a trusted team of talent we call on regularly, which keeps our sites fresh and lets us grow with each project. No job is too big, and we're always glad to hear from great people who'd like to work with us.</p>
    </div>
  </section>''', current="/contact/")

page("/contact/thanks/", "Thanks | Agency Eagle", "Thanks for contacting Agency Eagle.",
'''  <section class="page-head">
    <div class="container">
      <h1>Thanks, we got your message</h1>
      <p>We'll get back to you within one business day. <a href="/">Back to home</a></p>
    </div>
  </section>''', noindex=True)

AI_FAQ = {"@context": "https://schema.org", "@type": "FAQPage", "mainEntity": [
  {"@type": "Question", "name": q, "acceptedAnswer": {"@type": "Answer", "text": a}} for q, a in [
    ("Is the AI-Ready Site Score free?", "Yes. It's free, takes about two minutes, and there is nothing to install."),
    ("Why do you need my email?", "Your full report is emailed to you so you can share it with whoever runs your website. We may follow up a few times with tips. Every email has a one-click unsubscribe."),
    ("Does the check change anything on my website?", "No. It reads your public pages the same way a search engine does. It never logs in or changes anything."),
    ("Why does Bing matter for AI?", "ChatGPT search and Microsoft Copilot answer from Bing's index. A site that Bing can't crawl or hasn't indexed is invisible to them, even if it ranks on Google."),
    ("What is llms.txt?", "A short text file at the root of your site that tells AI tools what your business does and which pages matter most. It's new, and few sites have one yet."),
  ]]}
AI_CHECKS = [
  ("Can AI crawlers reach you?", "HTTPS, robots.txt, and whether Googlebot, Bingbot, ChatGPT, Claude, Perplexity, Gemini and Apple are allowed in."),
  ("Can they index you?", "No accidental noindex tags, an XML sitemap that robots.txt points to, and one official address per page."),
  ("Do they understand who you are?", "Page titles, descriptions, headings and schema.org structured data that says what kind of business you are."),
  ("Ready for AI answers", "An llms.txt guide, mobile setup, speed, link previews and image descriptions."),
  ("What a robot can't see", "Ten quick questions on Bing Webmaster Tools, Search Console, Google Business Profile, Bing Places, reviews and your content."),
]
checks_html = "\n".join(f"""        <article class="card">
          <h3>{t}</h3>
          <p>{d}</p>
        </article>""" for t, d in AI_CHECKS)
page("/ai-ready/", "Free AI-Ready Site Score: Can AI Find You? | Agency Eagle",
 "Can ChatGPT, Copilot and Google's AI find your business? Get a free AI-Ready Site Score: a live check of robots.txt, Bing, sitemap and schema.",
f'''  <section class="hero quiz-hero">
    <div class="container hero-grid">
      <div>
        <p class="eyebrow">Free AI-Ready Site Score</p>
        <h1>Can ChatGPT, Copilot and Google's AI find your business?</h1>
        <p class="lede">More customers now ask an AI assistant instead of searching. If those assistants can't read your site, they recommend someone else. Find out where you stand in about two minutes.</p>
        <ul class="checks">
          <li>A live check of your site, the way Bing and AI crawlers see it</li>
          <li>Ten quick questions about the things a robot can't see</li>
          <li>Your score out of 100 and the five fixes that matter most</li>
        </ul>
      </div>
      <div class="quiz-card" id="quiz" aria-live="polite">
        <form class="contact-form quiz-gate" id="quiz-gate" novalidate>
          <h2>Get my free score</h2>
          <p class="form-note">Your report is emailed to you. No credit card.</p>
          <p class="form-error" id="err-form" hidden></p>
          <label for="q-site">Your website</label>
          <input id="q-site" name="site" inputmode="url" autocomplete="url" placeholder="yourbusiness.com" required>
          <p class="form-error" id="err-site" hidden></p>
          <label for="q-name">Your name</label>
          <input id="q-name" name="name" autocomplete="name" required>
          <p class="form-error" id="err-name" hidden></p>
          <label for="q-email">Email</label>
          <input id="q-email" name="email" type="email" autocomplete="email" required>
          <p class="form-error" id="err-email" hidden></p>
          <label for="q-business">Business name (optional)</label>
          <input id="q-business" name="business" autocomplete="organization">
          <label for="q-phone">Phone (optional)</label>
          <input id="q-phone" name="phone" type="tel" autocomplete="tel">
          <div class="hp" aria-hidden="true"><label for="q-company-url">Leave this empty</label><input id="q-company-url" name="company_url" tabindex="-1" autocomplete="off"></div>
          <button class="button cta-button big" type="submit">Start my score</button>
          <noscript><p class="form-error">The score needs JavaScript. Call {PHONE} and we'll run it for you.</p></noscript>
        </form>
      </div>
    </div>
  </section>
  <section>
    <div class="container">
      <h2 class="section-title">What we check</h2>
      <div class="cards">
{checks_html}
      </div>
    </div>
  </section>
  <section class="band">
    <div class="container prose">
      <h2>Why Bing matters more than it used to</h2>
      <p>ChatGPT search and Microsoft Copilot answer from Bing's index. Many sites that rank fine on Google have never been checked in Bing Webmaster Tools, block newer crawlers by accident, or have no sitemap Bing can find. Those sites don't show up when someone asks an assistant for a recommendation.</p>
      <p>The fixes are usually small: a few lines in robots.txt, a sitemap, structured data, and pages that answer real questions. Our score shows you which ones you need.</p>
    </div>
  </section>
  <section>
    <div class="container faq prose">
      <h2>Questions</h2>
{chr(10).join(f"      <h3>{x['name']}</h3>{chr(10)}      <p>{x['acceptedAnswer']['text']}</p>" for x in AI_FAQ["mainEntity"])}
    </div>
  </section>''', current="/ai-ready/", extra_ld=AI_FAQ, script="/assets/ai-ready.js")

page("/ai-ready/report/", "Your AI-Ready Site Score | Agency Eagle", "Your AI-Ready Site Score report from Agency Eagle.",
'''  <section class="page-head">
    <div class="container">
      <p class="eyebrow">AI-Ready Site Score</p>
      <h1 id="report-title">Your report</h1>
      <p id="report-sub">Loading your report&hellip;</p>
    </div>
  </section>
  <section>
    <div class="container" id="report" aria-live="polite">
      <noscript><p>Your report needs JavaScript to display. It was also emailed to you.</p></noscript>
    </div>
  </section>''', noindex=True, script="/assets/ai-ready.js")

page("/404.html", "Page not found | Agency Eagle", "That page could not be found.",
'''  <section class="page-head">
    <div class="container">
      <h1>Page not found</h1>
      <p>That page doesn't exist anymore. Try the <a href="/">home page</a>, <a href="/services/">services</a> or <a href="/contact/">contact us</a>.</p>
    </div>
  </section>''', noindex=True)
