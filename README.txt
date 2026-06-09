=== Motoplus v2 ===
Version: 2.0.0

A professional WordPress car dealer plugin with live AJAX filtering,
photo gallery, lead capture, UsedCarsNI importer, and AI descriptions.

== INSTALLATION ==
1. Upload the 'motoplus-v2' folder to /wp-content/plugins/
2. Activate in WordPress → Plugins
3. Go to Motoplus → Dashboard for an overview

== FIRST STEPS ==
1. Motoplus → Settings — add your phone number, dealer name, brand colour
2. Motoplus → Add New Vehicle — start adding your stock
3. Place [motoplus_stock] on your Cars for Sale page

== SHORTCODES ==
[motoplus_stock]                  Full stock page with search & live filters
[motoplus_stock limit="12"]       Limit the number of cars shown
[motoplus_featured]               Featured vehicles only (default 3)
[motoplus_featured limit="6"]     Up to 6 featured vehicles
[motoplus_latest limit="6"]       Latest arrivals
[motoplus_search]                 Standalone search bar (e.g. homepage hero)

== IMPORTING VEHICLES ==
Motoplus → Import Vehicle

Option 1 – URL: paste the UsedCarsNI listing URL and click Import.
Option 2 – HTML: open the listing in your browser, View Page Source,
  copy all, paste into the HTML box. More reliable if URL import is blocked.

Imported vehicles are saved as DRAFTS. Review all fields, add a
description if needed, then Publish.

== REGISTRATION LOOKUP ==
When editing a vehicle, enter the registration and click Lookup Vehicle.
Set your lookup provider and API key in Motoplus → Settings.
Currently supports DVLA VES API. Manual entry always works without a key.

== AI DESCRIPTIONS ==
Fill in the vehicle fields, then click Generate Draft Description.
The text will appear in the main content editor for you to review.
Optionally add your OpenAI API key in Settings for GPT-4o-mini descriptions.

== SETTINGS ==
Brand colours, border radius, dealer phone, lead email, stock page URL,
lookup provider, and OpenAI key are all in Motoplus → Settings.

== FILE STRUCTURE ==
motoplus-v2/
  motoplus.php              Main plugin file
  includes/
    helpers.php             Shared functions and field definitions
    post-type.php           Vehicle & Lead post types, admin columns
    meta-boxes.php          Vehicle & Lead meta boxes, save handlers
    shortcodes.php          [motoplus_stock] and related shortcodes
    ajax.php                AJAX: filter, lead submit, lookup, import, AI
    single-template.php     Single vehicle page output
  admin/
    settings.php            Settings & admin menu
    dashboard.php           Dashboard page
    import.php              Import page UI
    admin.css               Admin styles
    admin.js                Admin JavaScript (gallery, lookup, AI, import)
  public/
    css/front.css           Frontend styles (uses CSS variables from settings)
    js/front.js             Frontend JavaScript (filter, gallery, enquiry)
    img/no-image.svg        Placeholder image
