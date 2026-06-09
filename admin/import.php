<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function motoplus_import_page() {
    ?>
    <div class="wrap mp-import-wrap">
        <h1>⬇ Import Vehicle</h1>
        <p class="mp-import-intro">Import vehicles from UsedCarsNI. Vehicles are saved as <strong>drafts</strong> so you can review them before publishing. Only import vehicles you own or have permission to use.</p>

        <div class="mp-import-grid">
            <div class="mp-import-card">
                <h2>📋 Paste HTML Source</h2>
                <p>Recommended if the URL import fails. Open the listing in your browser, right-click and choose <strong>View Page Source</strong>, select all, then paste below.</p>
                <div class="mp-import-field">
                    <label>Original Listing URL <em>(optional, helps with images)</em></label>
                    <input type="url" id="mp-import-source-url" class="mp-import-url-input" placeholder="https://www.usedcarsni.com/..." />
                </div>
                <div class="mp-import-field">
                    <label>Paste Full Page HTML</label>
                    <textarea id="mp-import-html" rows="12" placeholder="Paste full page source here..."></textarea>
                </div>
                <button class="mp-btn mp-btn--primary" id="mp-import-html-btn">Extract from HTML</button>
                <span class="mp-import-result" id="mp-import-html-result"></span>
            </div>

            <div class="mp-import-card">
                <h2>🔗 Import from URL</h2>
                <p>Fetch the listing directly. Some sites may block this — use the HTML paste method if you get an error.</p>
                <div class="mp-import-field">
                    <label>Listing URL</label>
                    <input type="url" id="mp-import-url" class="mp-import-url-input" placeholder="https://www.usedcarsni.com/..." />
                </div>
                <button class="mp-btn mp-btn--primary" id="mp-import-url-btn">Import from URL</button>
                <span class="mp-import-result" id="mp-import-url-result"></span>
            </div>
        </div>

        <div id="mp-import-preview" class="mp-import-preview"></div>
    </div>
    <?php
}
