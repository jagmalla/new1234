ACCESS LOG
==========

This folder holds the website's visit log. It is created and written to
automatically by the software — you do not need to do anything.

FILE TO READ:  access.log
--------------------------
Open this file (in your hosting File Manager, or download it) to see every
visit. Each visit is one line:

    (2) Ludhiana, Punjab, India | 07-07-2026 | 14:30 | 1h 05m | 203.0.113.45

Reading a line, left to right:

    (2)                       -> how many times this IP has opened the site.
                                 First visit has NO number. Second visit shows
                                 (2), third (3), and so on. So you can see how
                                 many times the same person came back.
    Ludhiana, Punjab, India   -> Location: city, province, country (from the IP)
    07-07-2026                -> Date, DD-MM-YYYY
    14:30                     -> Time, 24-hour clock
    1h 05m                    -> How long the person stayed on the website
    203.0.113.45              -> The visitor's IP address

Notes
-----
- Location is looked up from the IP address using a free public service. If the
  lookup can't run (server offline, or a private/local IP), it shows
  "Unknown" or "Local network" but the rest of the line is still recorded.
- The duration updates while the visitor is on the page and is finalised when
  they leave.
- Two hidden helper files (.visits.json and .geo_cache.json) are used by the
  software to track ongoing visits and cache locations. You can ignore them.

This folder sits OUTSIDE the public web folder, so visitors cannot see the log
in their browser — only you can, from the hosting file manager.
