# Modifications & Fixes Report: `local_qlogin_shomokh`

---

## 1. Overview of Work Accomplished

During testing of the re-engineered version of **`local_qlogin_shomokh`** (v0.6.2), we identified and resolved **5 critical issues** spanning PHP 8 fatal errors, JavaScript UI positioning anomalies, CSS layout clipping, Moodle Core API compliance, and self-service account linking logic.

All 40+ PHP files in the plugin now pass strict PHP syntax checking (`php -l`), and the plugin is stable, responsive, and ready for production testing.

---

## 2. Detailed Breakdown of Modifications & Fixes

---

### Fix 1: Fatal Error `Undefined constant PARAM_DIGITS` (PHP 8 Compatibility)

* **Symptom:**  
  Loading `/local/qlogin_shomokh/index.php` resulted in a fatal Moodle exception:  
  `Exception - Undefined constant "local_qlogin_shomokh\form\PARAM_DIGITS"` at `auth_form.php:33`.
* **Root Cause:**  
  `PARAM_DIGITS` is not a standard Moodle constant in `formslib.php`. In PHP 8.0+, referencing an undefined constant throws a fatal `Error` instead of falling back to a string notice.
* **Files Modified:**
  1. `classes/form/auth_form.php` (Line 33)
  2. `classes/form/link_existing_form.php` (Line 56)
  3. `classes/form/recovery_start_form.php` (Line 19)
* **Solution:**  
  Replaced all occurrences of `PARAM_DIGITS` with Moodle's standard `PARAM_ALPHANUM`.

```diff
- $mform->setType('phonecountrycode', PARAM_DIGITS);
+ $mform->setType('phonecountrycode', PARAM_ALPHANUM);
```

---

### Fix 2: Country Dropdown Displaced Outside Login Card

* **Symptom:**  
  Clicking the country flag inside the phone input box opened the dropdown menu floating completely outside the login card to the right.
* **Root Cause:**  
  In `qlogin_phone.js`, `intl-tel-input` was initialized with:
  ```javascript
  dropdownContainer: root || document.body
  ```
  Attaching the dropdown to the root flexbox wrapper forced JavaScript absolute positioning based on `getBoundingClientRect()` relative to the centered flex container, causing offset miscalculations.
* **Files Modified:**
  1. `qlogin_phone.js` (Lines 143–175)
  2. `qlogin_styles.css` (Lines 389–393)
* **Solution:**  
  - Removed `dropdownContainer` from `qlogin_phone.js` so `intl-tel-input` docks `.iti__country-list` directly inside `.iti`.
  - Added `position: relative; width: 100%;` to `#qlogin-wrapper .iti` in `qlogin_styles.css`.

---

### Fix 3: Country Dropdown Compressed into a Narrow Vertical Column

* **Symptom:**  
  After docking inside the card, the country list appeared as a narrow ~70px column with letters wrapping vertically (`S \n l \n o \n v \n a \n k...`).
* **Root Cause:**  
  In `intl-tel-input`, `.iti__country-list` is a child of the flag button (`.iti__flag-container`), which is only ~70px wide. Setting `width: 100%` and `white-space: normal` compressed the entire list to 70px and broke single-line country names.
* **Files Modified:**
  1. `qlogin_styles.css` (Lines 412–445)
* **Solution:**  
  - Set `width: min(340px, calc(100vw - 40px)) !important;` to match the exact width of the input field on the card.
  - Set `white-space: nowrap;` so country names stay on one row.
  - Implemented a clean flex row layout with the flag on the left, country name in bold, dialing code aligned to the right (`margin-left: auto`), and smooth scrolling (`max-height: 220px; overflow-y: auto;`).

```css
#qlogin-wrapper .iti__country-list {
    background: #fff !important;
    border: 1px solid #c8d3dc !important;
    border-radius: 6px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15) !important;
    color: #17212b !important;
    direction: ltr;
    font-size: 0.9rem;
    left: 0;
    margin-top: 4px;
    max-height: 220px;
    overflow-y: auto;
    text-align: left;
    white-space: nowrap;
    width: min(340px, calc(100vw - 40px)) !important;
    z-index: 1050;
}
```

---

### Fix 4: Moodle Developer Debugging Warnings on Name Fields

* **Symptom:**  
  Opening `/local/qlogin_shomokh/manage.php` output Moodle developer debugging warnings:  
  `The following name fields are missing from the user object: firstnamephonetic, lastnamephonetic, middlename, alternatename`.
* **Root Cause:**  
  The verification queue query in `manage.php` and the migration scanner in `classes/migration.php` only selected `u.firstname, u.lastname`. When `fullname($user)` was called, Moodle's name display engine warned that phonetic and middle name fields were missing.
* **Files Modified:**
  1. `manage.php` (Lines 45–53)
  2. `classes/migration.php` (Lines 214–224)
* **Solution:**  
  Integrated Moodle's standard `\core_user\fields::for_name()` API to dynamically select all required name fields.

```php
$nameselects = class_exists('\core_user\fields')
    ? \core_user\fields::for_name()->get_sql('u')->selects
    : 'u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename';
```

---

### Fix 5: Self-Service Phone Linking Rejection (`claim:noteligible`)

* **Symptom:**  
  Submitting the "Link an existing account" form on `link_existing.php` returned:  
  `"This account cannot use self-service linking. Sign in normally or contact support."`
* **Root Causes:**  
  1. **Unset Settings Defaulting to False:** `(bool)get_config('local_qlogin_shomokh', 'selfclaimenabled')` returned `false` because newly added settings return `false` from Moodle's database until explicitly saved in Site Administration.
  2. **Strict Auth Method Check:** `can_self_claim()` strictly required `auth = 'manual'`, rejecting users registered via Moodle's standard email self-registration (`auth = 'email'`).
* **Files Modified:**
  1. `classes/migration.php` (Lines 12–35, 140–158)
  2. `settings.php` (Lines 60–63)
* **Solution:**  
  - Updated `self_claim_available()` to treat unconfigured settings as active (`true`) by default.
  - Added `'email'` alongside `'manual'` to the default allowed authentication types across all check functions.

---

## 3. Summary Table of Files Modified

| File Path | Description of Changes |
| :--- | :--- |
| `classes/form/auth_form.php` | Fixed `PARAM_DIGITS` fatal error |
| `classes/form/link_existing_form.php` | Fixed `PARAM_DIGITS` fatal error |
| `classes/form/recovery_start_form.php` | Fixed `PARAM_DIGITS` fatal error |
| `qlogin_phone.js` | Docked country dropdown inside input container; updated query selectors |
| `qlogin_styles.css` | Full-width dropdown layout, `white-space: nowrap`, flex alignment & elevation |
| `manage.php` | Integrated `\core_user\fields::for_name()` API |
| `classes/migration.php` | Added name fields; fixed config defaults; allowed `email` + `manual` |
| `settings.php` | Added `email` to default `authtypes` |
| `version.php` | Incremented version number (`2026081718`) for cache busting |
