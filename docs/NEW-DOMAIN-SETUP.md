# New Domain Setup: from a signed client to a live site

**What this is.** The order Agency Eagle follows to put a new client (or a new idea of ours) on a live, HTTPS, HTML-only site: domain, DNS, cPanel account, SSL, splash page, code home, deploy, mail, standards check, and only then go public. It is adapted from the Cedar Pond Farms "Concept Lab" playbook and filled in with how our own stack works. Items marked **[CONFIRM]** still need checking against the real setup.

Most of these steps already run from the admin at `https://agencyeagle.com/admin/` (**Sites → Activate new site**, then the site's own page). This doc says what each step is, who does it, and how we know it's done.

## Rules that never bend

- No password, token or private key goes into chat, a PR, a GitHub log, an issue or a browser console. Generated passwords go to 1Password and are never shown in a thread. (The deploy workflow masks its one-time FTP password with `::add-mask::`.)
- Every step checks current state first and is safe to run twice. A step is done only when its check passes.
- Every new site ships `noindex` until the client (and we) approve going public.
- Domain registration stays manual. We never buy a domain or change a registrar on our own.
- HTML only. No WordPress, no page builders. Every site has to pass [`agencyeagle-site/STANDARDS.md`](../agencyeagle-site/STANDARDS.md).
- Anything destructive (terminating an account, a deploy with **wipe**, deleting files, making a repo public) needs a yes from Shawnio first.

## Who does what

| Marker | Meaning |
|---|---|
| 🤖 | The admin, a GitHub Action or Claude does it |
| 🧑 | Only a person can do it: registrar, passwords and 2FA, mailbox, approvals |

## Our setup (the facts the steps rely on)

| Thing | Value |
|---|---|
| Web server (WHM) | `host.agencyeagle.com`, WHM on port 2087, cPanel on 2083 |
| Server IP for A records | `69.16.237.175` (the `AE_SERVER_IP` constant in `_private/app/whm.php`) **[CONFIRM still current before each new domain]** |
| Account owner | New accounts are created by the `lagunait` reseller token, so the admin can see and manage them. Root-owned accounts are invisible to the admin. |
| Open ports | 21, 443, 2083, 2087. Port 22 (SSH/SFTP) is firewalled to approved IPs only. |
| Deploys | Over the WHM/cPanel API or FTPS. No SSH deploy keys. |
| Passwords | 1Password. Shawnio types them; Claude never sees them. |
| Code | Private repos in the `AgencyEagleLLC` GitHub org. Never public, never "internal". |
| Old Windows host | `carol.agencyeagle.com` (classic ASP + SQL Server, no cPanel). Only for legacy sites being moved off. |

## The order

| # | Step | Who | How we do it | Done when (the check) |
|---|---|---|---|---|
| 1 | **Domain.** Check it's available, then register it, or confirm the client already owns it and who controls its DNS. | 🧑 registers, 🤖 checks | Availability lookup (GoDaddy or a registry `whois`). Registration is by hand at the registrar. | A `whois` shows the domain registered to the client or to us, and we know where its DNS is hosted |
| 2 | **cPanel account.** One account per site. The username comes from the domain (`newclient.com` → `newclient`), docroot `/home/<user>/public_html`, on the client's package. | 🤖 | Admin → **Activate new site** (WHM `createacct` through the lagunait token). The random password goes to 1Password. Safe to run twice: if the domain already has an account, Activate keeps it, publishes nothing over its files, and only re-adds the client's sign-in and restarts AutoSSL. | The account shows on the admin **Sites** page |
| 3 | **Splash page.** A one-page "new website on the way" with the business name, email and phone, marked `noindex`. | 🤖 | Activate publishes it with the WHM file API. | `https://<domain>/` (once DNS points here) shows the splash |
| 4 | **Client /admin.** Installs `<domain>/admin`, where the client signs in with Google to reach their portal: SEO brain, backups, Site Brief. | 🤖 | Activate installs the stub and saves the client's email. | Signing in at `<domain>/admin` with the client's Google account lands in the portal |
| 5 | **DNS.** The `A` records for the apex and `www` point at the server IP (or `www` is a CNAME to the apex). | 🧑 sets the records at the client's DNS host (Claude does it in Chrome when we have the login), 🤖 verifies | Site page → **Setup checks** → Run setup checks. | `A` for the apex and `www` both resolve to `69.16.237.175` |
| 6 | **SSL.** Run AutoSSL once DNS resolves. | 🤖 | Activate starts AutoSSL right away; run it again from the site page after DNS is set. **Setup checks** verifies the certificate against this server and shows its expiry, plus the http → https redirect. | `https://<domain>/` and `https://www.<domain>/` return 200 with a valid certificate, and `http://` redirects to `https://` |
| 7 | **Code home.** For a done-for-you build ($5,000), the site's source goes in a **private** repo in `AgencyEagleLLC`. For a portal client who edits through `/admin`, the portal's backups are the history and no repo is needed. Some clients (Rob Wilmoth, preferredsurg.com) chose no repo at all. | 🤖 | Repo created by the GitHub App, private, with `public_html/` at the top level. | The repo exists and is private |
| 8 | **Deploy.** Repo sites deploy with a GitHub Action over FTPS, the same pattern as agencyeagle.com: each run uses the WHM token to give a deploy-only FTP login a fresh random password, so no FTP password is stored anywhere. Portal sites deploy through `/admin` uploads, which run the standards check and take a snapshot first. | 🤖 | Copy `.github/workflows/deploy-agencyeagle-site.yml`, change the cPanel user, FTP login and domain. Secrets: `WHM_USER`, `WHM_API_TOKEN`, `FTP_SERVER`. | The live page's rev stamp matches the repo's latest commit (see below) |
| 9 | **Mail.** Decide where the client's email lives (cPanel mail on our server, Google Workspace or Microsoft 365). Set MX, SPF, and DKIM/DMARC where the provider supports it. | 🧑 creates mailboxes and approves the provider, 🤖 sets and verifies records | cPanel Email Deliverability for mail on our server; the provider's records otherwise. **Setup checks** shows MX, SPF (flags more than one) and DMARC. | MX resolves to the right provider, SPF passes, and a test message to the contact address is accepted |
| 10 | **Claude scaffolding PR** (repo sites only). `CLAUDE.md` with the site's rules (HTML only, standards, no secrets, noindex until approved), a link to the Site Brief, and the deploy workflow. Opened as a PR, never pushed straight to the main branch. | 🤖 | PR in the new repo. | The PR is open and its checks are green |
| 11 | **Standards and noindex.** Every page passes `check_site.py` (Block items stop a deploy) and still carries `<meta name="robots" content="noindex">`. | 🤖 builds, 🧑 approves the content | `python3 agencyeagle-site/public_html/_private/tools/check_site.py <public_html>` | The checker is green, and noindex is on every page |
| 12 | **Go public.** Remove `noindex`, make sure `robots.txt` and `sitemap.xml` are right, then press **Go live** on the site page. That announces the site to search engines (IndexNow), runs the first SEO score, site watch and backup, and emails the client their `/admin` link. **Announce** refuses while the home page is still `noindex`, so the splash can't be announced by mistake. | 🧑 says yes, 🤖 does it | Site page → **Go live** card. | Shawnio's (and the client's) yes for that domain, then the Go live card shows done |

Then, after launch: Site watch (file changes and uptime) and nightly backups cover the site from the admin. Add Google Search Console and Bing Webmaster Tools for the client's domain when they're on the Growth plan.

## Verify a deploy landed (ten seconds)

Repo deploys write `/rev.txt` with the short commit hash and time. agencyeagle.com's deploy workflow then loads `https://agencyeagle.com/rev.txt` and fails if it doesn't match the commit it shipped. For a quick look by hand, load `/rev.txt` on the live site. If the stamp is older than the repo, the deploy didn't run or didn't reach the right docroot. That isn't a cache problem. Prove which account and docroot you're looking at before blaming the cache, because addon domains and old folders in `public_html` can serve a different copy.

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| A cloud Claude thread says "connection refused" for the server | The cloud environment's network policy, not the server | Add the host to the allowed domains in the project's cloud environment |
| AutoSSL fails or never issues | DNS doesn't point here yet, or `www` is missing | Fix step 5, wait for it to resolve, run AutoSSL again from the site page |
| The site isn't on the admin Sites page | The account is owned by root, not lagunait | Move it to the lagunait reseller in WHM (with Shawnio's yes) |
| Deploy logs in as the wrong user or gets "Login authentication failed" | The cPanel username is the **account** name, not the domain. A renamed domain keeps its old account name. | Use the account name in the workflow |
| SSH or SFTP times out | Port 22 is firewalled to approved IPs | Deploy over FTPS or the API instead. Don't open port 22. |
| The client's FTP login can't see `public_html` | Sub-account FTP logins are jailed to their own folder | Set the FTP login's home to `public_html` when it's created |
| The live page shows an older version | The deploy didn't reach the docroot, or an addon domain points at a different folder | Check the account's domain list on the Sites page, then deploy again |
| An old PHP site returns HTTP 500 | The server runs PHP 8.x, and old Joomla/WordPress won't run on it | Rebuild it in HTML. Don't install an end-of-life PHP. |

## Open [CONFIRM] items

1. Is `69.16.237.175` (`host.agencyeagle.com`) still the only server for new sites, or should some go on the second Liquid Web server?
2. Which DNS host and registrar each new client uses, and whether we have the login or the client sets the records.
3. Default mail provider for new clients: cPanel mail on our server, or Google Workspace/M365.

---
*Adapted on 2026-11-08 from Cedar Pond Farms `docs/concept-lab/NEW-DOMAIN-SETUP.md` (written 2026-10-26).*
