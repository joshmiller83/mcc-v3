# Domains and DNS

_Part of the agent guidance indexed in [AGENTS.md](../../AGENTS.md). Read it before working on this area._

## Where things live

| What | Where |
| ---- | ----- |
| Registrar for `mechanicsburgchristian.com` | **eNom** (Tucows), registered 2005-11-16, expires 2027-11-16, transfer-locked |
| DNS hosting | eNom's own free DNS, `dns1`–`dns5.name-services.com`. Edited in the eNom account, not at Pantheon |
| `mechanicsburgchristian.com`, `www.` | the D7 site, `mcc-church.live` (apex is primary) |
| `new.mechanicsburgchristian.com` | this rebuild, `mcc2026.live` (added 2026-10-31) |

The eNom account itself is not recorded anywhere in the repo or in the user's mail: RDAP
redacts the registrant, so whoever registered the domain in 2005 gets the renewal notices.
The zone has no MX record, so DNS changes here cannot break church email (it runs on Gmail
addresses, not on this domain).

## Rules

- **The zone has a wildcard AAAA (`*.mechanicsburgchristian.com` → Pantheon's two IPv6
  addresses) and no wildcard A, so a new subdomain needs its A *and* both AAAA records added
  explicitly.** Seen 2026-10-31: `terminus domain:dns` reported the AAAA for `new.` as
  "Correct value detected" before anything had been added. That came from the wildcard, which
  stops applying to a name once that name has a record of its own (RFC 4592). Add only the A
  and `new.` loses IPv6. Pantheon's values for any domain are in
  `terminus domain:dns <site>.<env>`: A `23.185.0.1`, AAAA `2620:12a:8000::1` and
  `2620:12a:8001::1`.
- **Pantheon wants proof of ownership before it serves a custom domain or issues its
  certificate.** `terminus domain:verify <site>.<env> <domain>` prints the TXT record it wants
  (`_acme-challenge.<domain>`) on the first run and checks for it on the next. Neither command
  shows the value until you ask. In eNom's editor the host name has no trailing dot and no
  domain suffix (`_acme-challenge.new`, not `_acme-challenge.new.mechanicsburgchristian.com.`).
- **Custom domains need a paid plan, and Basic allows five per site across all environments.**
  mcc2026 went from sandbox to Basic on 2026-10-31. `mcc-church` (D7) has its own five.
- **A custom domain on mcc2026 is crawlable.** Pantheon's blanket `Disallow` robots.txt only
  covers the `*.pantheonsite.io` platform domains. Until the rebuild launches on the apex,
  anything served at `new.` competes with the D7 site in search.
