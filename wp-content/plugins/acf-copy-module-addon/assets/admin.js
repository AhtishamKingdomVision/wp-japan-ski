(function ($) {
  /** Simple toast instead of blocking alert */
  function toast(msg, type = 'info') {
    const $toast = $('<div class="atl-toast"></div>').text(msg);
    $toast.css({
      position: 'fixed',
      bottom: '20px',
      right: '20px',
      background: type === 'error' ? '#dc3545' : '#0073aa',
      color: '#fff',
      padding: '10px 15px',
      borderRadius: '4px',
      zIndex: 99999,
      fontSize: '13px',
      boxShadow: '0 2px 5px rgba(0,0,0,0.2)',
    });
    $('body').append($toast);
    setTimeout(() => $toast.fadeOut(400, () => $toast.remove()), 2200);
  }

  /** 🧠 Inject Save/Insert buttons */
  function injectModuleButtons($context = $(document)) {
    console.log('🧩 Injecting Module Buttons...');

    $context.find('.acf-field-flexible-content').each(function () {
      const $fc = $(this);

      // --- INSERT BUTTON ---
      if ($fc.find('> .acf-input > .acf-flexible-content > .atl-actions-bar').length === 0) {
        const $actionsBar = $(`
        <div class="atl-actions-bar" style="margin-bottom:10px;">
          <button type="button" class="button atl-insert-module">Insert Module</button>
        </div>
        `);
        $fc.find('> .acf-input > .acf-flexible-content > .values').before($actionsBar);
        console.log('✅ Insert button added');
      }

      // --- SAVE BUTTON ---
      $fc.find('.layout').each(function () {
        const $layout = $(this);
        const $title = $layout.find('> .acf-fc-layout-handle, > .acf-fc-layout-title');

        if ($title.find('.atl-save-module').length === 0) {
          const $btn = $('<button type="button" class="button atl-save-module" style="margin-left:8px;">💾 Save Module</button>');
          // ACF 6.x: add after layout title or controls
          const $controls = $layout.find('.acf-fc-layout-controls');
          if ($controls.length) {
            $controls.append($btn);
          } else {
            $title.append($btn);
          }
          console.log('✅ Save button added for layout:', $layout.data('layout'));
        }
      });
    });
  }


  $(document).ready(() => {
    injectModuleButtons();
    acf.addAction('append', injectModuleButtons);
  });



  $(document).on('click', '.atl-save-module', async function () {

    console.clear();
    console.log("🟦 [ACFML] Save Module clicked");

    const $layout = $(this).closest('.layout');
    const layoutName = $layout.data('layout');
    const rowId = $layout.data('id');
    const $flex = $layout.closest('.acf-field-flexible-content');
    const groupKey = $flex.data('key');

    if (!rowId) {
      alert("ACF row ID missing — cannot save layout.");
      return;
    }

    console.log("➡️ Layout:", layoutName, "Row ID:", rowId);

    // / Get ONLY the visible module title
    let layoutTitle = $layout
      .find('.acf-fc-layout-title')
      .text()
      .trim();

    // Remove leading order number like "2 "
    layoutTitle = layoutTitle.replace(/^\d+\s*/, "");

    // Fallback if empty
    if (!layoutTitle) {
      layoutTitle = layoutName.replace(/_/g, " ");
    }

    const name = prompt("Enter module name:", layoutTitle);
    if (!name) return;
    if (!name) return;

    // Serialize layout
    let raw = acf.serialize($layout);
    raw = raw.acf ? raw.acf : raw;

    console.log("🧪 RAW SERIALIZED:", raw);

    // ------------------------------
    // IMAGE NORMALIZER
    // ------------------------------
    async function normalizeImage(val) {
      if (!val) return null;

      // Already object? return as-is.
      if (typeof val === "object" && val.id) {
        return val;
      }

      // Otherwise treat as image ID
      const id = parseInt(val);
      if (!id) return null;

      const att = wp.media.attachment(id);
      await att.fetch();

      return {
        id: id,
        url: att.get("url"),
        alt: att.get("alt") || "",
        title: att.get("title") || ""
      };
    }

    // Detect if field is image by checking DOM
    function isImageField(fieldKey) {
      return $layout.find(`.acf-field[data-key="${fieldKey}"][data-type="image"]`).length > 0;
    }

    // ------------------------------
    // FIELD EXTRACTOR (supports repeater)
    // ------------------------------
    async function extractFlexibleRow(rawObject, rowId) {
      let result = {};

      for (const groupValue of Object.values(rawObject)) {

        if (typeof groupValue !== "object") continue;

        const rowData = groupValue[rowId];
        if (!rowData) continue;

        // Loop through fields inside this row
        for (const [fieldKey, val] of Object.entries(rowData)) {

          if (fieldKey === "acf_fc_layout") continue;

          // 1. REPEATER
          if (typeof val === "object" && !Array.isArray(val)) {

            const nestedKeys = Object.keys(val).filter(k => k.startsWith("row-"));

            if (nestedKeys.length) {
              result[fieldKey] = [];

              for (const nestedRow of nestedKeys) {
                const inner = await extractFlexibleRow({ tmp: val }, nestedRow);
                result[fieldKey].push(inner);
              }

            } else {
              // Object field: link / image / group?
              if (isImageField(fieldKey)) {
                result[fieldKey] = await normalizeImage(val);
              } else {
                result[fieldKey] = val;
              }
            }

          } else {
            // 2. SIMPLE FIELD
            if (isImageField(fieldKey)) {
              result[fieldKey] = await normalizeImage(val);
            } else {
              result[fieldKey] = val;
            }
          }
        }
      }

      return result;
    }

    // Run extraction
    const fields = await extractFlexibleRow(raw, rowId);

    console.log("🎯 Extracted fields:", fields);

    // ------------------------------
    // BUILD FINAL MODULE DATA
    // ------------------------------
    const layoutData = {
      acf_fc_layout: layoutName,
      ...fields
    };

    console.log("📦 FINAL MODULE DATA:", layoutData);

    // Detect current post type
    let postType = null;

    try {
      // Modern WP (Gutenberg / classic)
      postType = wp.data.select("core/editor").getCurrentPostType();
    } catch (e) {
      // Fallback for non-Gutenberg
      postType = jQuery('#post_type').val() || jQuery('#post-form').attr("class");
    }

    if (!postType) postType = "unknown";

    console.log("📌 DETECTED POST TYPE:", postType);


    // SEND TO API
    const payload = {
      name,
      slug: name.toLowerCase().replace(/[^a-z0-9]+/g, "-"),
      type: "section",
      post_type: postType,         // ⭐ NEW — store post type
      acf_group_key: groupKey,
      data: [layoutData]
    };

    console.log("📤 Sending payload:", payload);

    try {
      const res = await fetch(`${ATL.rest}modules`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": ATL.nonce
        },
        credentials: "same-origin",
        body: JSON.stringify(payload)
      });

      const json = await res.json();
      console.log("📩 Server response:", json);

      toast(json.ok ? "✅ Module saved successfully" : "❌ Failed to save module");
    }
    catch (err) {
      console.error("💥 Save error:", err);
      toast("⚠️ Error saving module — check console", "error");
    }
  });



  /** 📦 INSERT MODULE */
  $(document).on('click', '.atl-insert-module', async function () {

    const $btnm = $(this);

    // 🔥 Disable button while loading modules
    $btnm.prop("disabled", true);
    const oldText = $btnm.text();
    $btnm.text("Loading...");
    try {

      const fieldKey = $(this).closest('.acf-field-flexible-content').data('key');
      const field = acf.getField(fieldKey);
      if (!field) return toast("⚠️ Can't find ACF field", "error");

      console.log("🔍 Loading saved modules...");

      const res = await fetch(`${ATL.rest}modules?type=section`, {
        credentials: 'same-origin',
        headers: { 'X-WP-Nonce': ATL.nonce }
      });

      if (!res.ok) return toast("⚠️ Failed loading modules", "error");

      let list = await res.json();
      if (!list?.length) return toast("⛔ No modules found");

      console.log("📦 Modules loaded:", list);

      /* -----------------------------------------
         Detect CURRENT POST TYPE
      ----------------------------------------- */
      let currentPostType = null;

      try {
        currentPostType = wp.data.select("core/editor").getCurrentPostType();
      } catch (e) {
        currentPostType = jQuery('#post_type').val() || 'unknown';
      }

      console.log("📌 Current Post Type:", currentPostType);

      /* -----------------------------------------
         Filter modules by post_type
      ----------------------------------------- */
      let filteredList = list.filter(m =>
        !m.post_type || m.post_type === currentPostType
      );

      if (!filteredList.length) {
        return toast("⛔ No modules available for this post type");
      }

      console.log("📦 Filtered modules:", filteredList);


      /* -----------------------------
         Module chooser modal
      ------------------------------*/
      const $modal = $(`
  <div class="atl-modal" style="display:block;">
    <div class="atl-modal-inner" style="
      background:#fff;
      padding:20px;
      border:1px solid #ccc;
      max-width:600px;
      margin:40px auto;
    ">
      <button class="button-link atl-modal-close" style="float:right;">×</button>
      <h2>Insert Module</h2>

      <!-- Search -->
      <input type="text" class="atl-search"
             placeholder="Search modules..."
             style="width:100%;margin-bottom:10px;"/>

      <div class="atl-list"></div>
    </div>
  </div>
`);

      const $list = $modal.find('.atl-list');


      /* -----------------------------
         Render module list
      ------------------------------*/
      function render(items) {
        $list.html(
          items.map(m => `
      <div class="atl-item" style="
          padding:8px 6px;
          border-bottom:1px solid #eee;
          display:flex;
          align-items:center;
          justify-content:space-between;
      ">
        
        <div style="flex:1;">
          <strong>${m.name}</strong>
          <code style="margin-left:6px;">${m.slug}</code>

          <!-- ⭐ POST TYPE BADGE -->
          <span style="
            margin-left:10px;
            background:#eef;
            padding:2px 6px;
            border-radius:4px;
            font-size:11px;
            color:#333;
          ">
            ${m.post_type || '—'}
          </span>
        </div>

        <button class="button insert" data-slug="${m.slug}">
          Insert
        </button>
      </div>
    `).join('')
        );
      }


      /* Initial list render */
      render(filteredList);

      /* Show modal */
      $('body').append($modal);


      /* -----------------------------
         Search filter (name, slug, post_type)
      ------------------------------*/
      $modal.on('input', '.atl-search', function () {
        const q = $(this).val().toLowerCase();

        const result = filteredList.filter(m =>
          (m.name?.toLowerCase().includes(q)) ||
          (m.slug?.toLowerCase().includes(q)) ||
          (m.post_type?.toLowerCase().includes(q))
        );

        render(result);
      });


      /* Close modal */
      $modal.on('click', '.atl-modal-close', () => $modal.remove());

      // $modal.on('click', '.atl-modal-close', () => $modal.remove());

      /* -----------------------------
         Insert click
      ------------------------------*/
      $modal.on('click', '.insert', async function () {

        const $btn = $(this);

        // 🔥 Disable & show loading text
        $btn.prop("disabled", true);
        const oldText = $btn.text();
        $btn.text("Loading...");

        try {
          const slug = $(this).data('slug');
          console.log(`📥 Fetching module: ${slug}`);



          const r = await fetch(`${ATL.rest}modules/${slug}`, {
            credentials: 'same-origin',
            headers: { 'X-WP-Nonce': ATL.nonce }
          });

          if (!r.ok) return toast('⚠️ Error fetching module', 'error');

          const mod = await r.json();
          if (!mod?.data) return toast('❌ Invalid module', 'error');

          console.log('📥 Inserting module:', mod);

          /* -------------------------------------------
             Universal field applier
          --------------------------------------------*/
          async function applyField($parent, fieldKey, fieldValue) {

            const $field = $parent.find(`.acf-field[data-key="${fieldKey}"]`);
            if (!$field.length) {
              console.warn(`⚠️ Field not found: ${fieldKey}`);
              return;
            }

            const type = $field.data('type');
            const obj = acf.getField(fieldKey, $parent);
            if (!obj) {
              console.warn(`⚠️ ACF object not found for: ${fieldKey}`);
              return;
            }


            function waitForSelect2($select) {
              return new Promise(resolve => {
                let attempts = 0;

                const check = () => {
                  attempts++;

                  const isReady =
                    $select.hasClass("select2-hidden-accessible") &&
                    $select.next(".select2").length > 0;

                  if (isReady) {
                    resolve();
                  } else if (attempts < 40) {
                    setTimeout(check, 50);
                  } else {
                    console.warn("⚠️ Select2 did not initialize in time.");
                    resolve(); // continue anyway
                  }
                };

                check();
              });
            }


            if (type === "relationship" || type === "post_object") {

              console.log(`🔗 Setting post_object/relationship → ${fieldKey}:`, fieldValue);

              let ids = Array.isArray(fieldValue) ? fieldValue : [fieldValue];

              // Clean invalid IDs
              ids = ids
                .map(v => parseInt(v))
                .filter(v => Number.isInteger(v) && v > 0);

              if (ids.length === 0) {
                console.warn(`⚠️ No valid IDs for ${fieldKey}. Skipping.`);
                obj.val([]);
                $field.find("select").empty().trigger("change");
                return;
              }

              const $select = $field.find("select");

              obj.val([]);
              $select.empty().append(`<option selected>Loading…</option>`);

              // Wait for Select2 to initialize
              const waitForAjax = async () => {
                for (let i = 0; i < 50; i++) {
                  let loaded = $select.data("select2")?.dataAdapter?.$container?.find(".select2-results__option").length;
                  if (loaded && loaded > 0) return true;
                  await new Promise(r => setTimeout(r, 100));
                }
                return false;
              };

              console.log("⏳ Waiting for Select2 to load…");
              await waitForAjax();

              $select.empty();

              for (const id of ids) {

                let title = `Post #${id}`; // default fallback

                try {
                  const post = await $.ajax({
                    url: `${window.location.origin}/wp-json/wp/v2/posts/${id}`,
                    method: "GET"
                  });

                  if (post?.title?.rendered) {
                    title = post.title.rendered;
                  }

                  console.log(`📄 Loaded title for ID ${id}:`, title);

                } catch (err) {
                  console.warn(`⚠️ Post ${id} returned 404 or failed. Using fallback.`);
                  // title stays fallback
                }

                // Always insert an option (even if post does not exist)
                $select.append(`<option value="${id}" selected="selected">${title}</option>`);
              }

              // Apply final value
              obj.val(ids);

              setTimeout(() => $select.trigger("change"), 50);

              console.log("✅ Relationship/post_object applied:", ids);
              return;
            }





            if (type === "repeater" && Array.isArray(fieldValue)) {

              console.groupCollapsed(`🔁 Repeater Insert → ${fieldKey}`);
              console.log("Incoming repeater data:", fieldValue);

              // Correct DOM container for the repeater
              const $fieldContainer = $parent.closest('.layout')
                .find(`.acf-field[data-key="${fieldKey}"]`)
                .first();

              console.log("📌 Detected repeater field container:", $fieldContainer);

              const repField = acf.getField($fieldContainer);

              if (!repField) {
                console.error(`❌ Could not find repeater object inside layout for: ${fieldKey}`);
                console.groupEnd();
                return;
              }

              console.log("✅ Repeater field found:", repField);

              await new Promise(r => setTimeout(r, 50));
              acf.doAction('ready_field', repField.$el);
              acf.doAction('append_field', repField.$el);

              // Add rows
              for (let i = 0; i < fieldValue.length; i++) {
                repField.add();
              }

              await new Promise(r => setTimeout(r, 150));

              const $rows = repField.$el.find('.acf-row:not(.acf-clone)');

              console.log(`📥 Rows found: ${$rows.length}`);

              // Fill rows
              $rows.each(function (rowIndex) {
                const rowData = fieldValue[rowIndex];
                const $row = $(this);

                console.log("🧩 Filling row", rowIndex, rowData);

                for (const subKey in rowData) {
                  const subValue = rowData[subKey];

                  // Find the EXACT subfield container
                  const $subField = $row.find(`.acf-field[data-key="${subKey}"]`).first();
                  if (!$subField.length) {
                    console.warn("⚠ Subfield not found in row:", subKey);
                    continue;
                  }

                  const subACF = acf.getField($subField);
                  if (!subACF) {
                    console.warn("⚠ No ACF object for subfield:", subKey);
                    continue;
                  }

                  console.log("✔ Setting", subKey, "→", subValue);

                  // ---- FIX FOR IMAGE FIELDS ----
                  if (subACF.data.type === "image") {

                    const imageId = subValue.id ? subValue.id : parseInt(subValue);
                    console.log("🖼 Setting repeater image:", subKey, imageId);

                    subACF.val(imageId);

                    const att = wp.media.attachment(imageId);
                    att.fetch().then(() => {
                      const data = att.attributes;

                      if (!data || !data.url) return;

                      const $uploader = $subField.find(".acf-image-uploader");
                      const $img = $uploader.find("img");

                      $img.attr("src", data.url);
                      $uploader.addClass("has-value");

                      acf.doAction("render_field", $subField);
                      console.log("✅ Repeater image loaded:", data.url);
                    });

                    continue;
                  }

                  // ---- FILE FIELD SUPPORT ----
                  if (subACF.data.type === "file") {

                    console.log("📁 Setting FILE field:", subKey, subValue);

                    const fileId = subValue.id ? parseInt(subValue.id) : parseInt(subValue);

                    // 1. Set raw ID for ACF
                    subACF.val(fileId);

                    // 2. Fetch attachment to update UI
                    const att = wp.media.attachment(fileId);

                    att.fetch().then(() => {
                      const data = att.attributes;

                      if (!data) return;

                      const $fileWrap = $subField.find('.acf-file-uploader');

                      // Fill hidden input
                      $fileWrap.find('input[type="hidden"]').val(fileId);

                      // UI Preview
                      $fileWrap.addClass('has-value');
                      $fileWrap.find('.file-icon').attr('src', data.icon);
                      $fileWrap.find('.file-info .file-title').text(data.title || data.filename);
                      $fileWrap.find('.file-info .file-name').text(data.filename);

                      console.log("📁 File field updated:", data);
                    });

                    continue;
                  }



                  // --- GROUP FIELD SUPPORT ---
                  if (subACF.data.type === "group" && typeof subValue === "object") {

                    console.log("📦 Setting GROUP field:", subKey, subValue);

                    // FIXED: correct group wrapper
                    const $groupWrapper = $subField.find(".acf-input .acf-fields").first();

                    for (const gKey in subValue) {
                      const gVal = subValue[gKey];

                      // Correct selector
                      const $gField = $groupWrapper
                        .find(`.acf-field[data-key="${gKey}"]`)
                        .first();

                      if (!$gField.length) {
                        console.warn("⚠ Group subfield missing:", gKey);
                        continue;
                      }

                      const gACF = acf.getField($gField);
                      if (!gACF) {
                        console.warn("⚠ Missing ACF object for group subfield:", gKey);
                        continue;
                      }

                      // IMAGE
                      if (gACF.data.type === "image") {
                        const imgId = gVal.id || null;
                        gACF.val(imgId);
                        continue;
                      }

                      // LINK
                      if (gACF.data.type === "link") {
                        gACF.val({
                          title: gVal?.title || "",
                          url: gVal?.url || "",
                          target: gVal?.target || ""
                        });
                        continue;
                      }

                      // WYSIWYG
                      if (gACF.data.type === "wysiwyg") {

                        gACF.val(gVal); // always set ACF raw value

                        const $textarea = $gField.find("textarea");
                        const editorID = $textarea.attr("id");

                        let tries = 0;
                        const max = 80; // up to 10 seconds; safe for flex/repeater load

                        const interval = setInterval(() => {
                          tries++;

                          const editor = tinyMCE?.get(editorID);

                          // CASE 1: Visual mode and TinyMCE ready
                          if (editor && !editor.isHidden()) {
                            editor.setContent(gVal || "");
                            clearInterval(interval);
                            return;
                          }

                          // CASE 2: TinyMCE exists but is in TEXT mode
                          if (editor && editor.isHidden()) {
                            $textarea.val(gVal || "");
                            clearInterval(interval);
                            return;
                          }

                          // CASE 3: TinyMCE not ready yet, but textarea visible → treat as Text Mode
                          if (!editor && $textarea.is(":visible")) {
                            $textarea.val(gVal || "");
                            clearInterval(interval);
                            return;
                          }

                          // CASE 4: Timeout → fallback to textarea silently
                          if (tries >= max) {
                            $textarea.val(gVal || "");
                            clearInterval(interval);
                            return;
                          }

                        }, 100);

                        continue;
                      }


                      // COLOR PICKER
                      if (gACF.data.type === "color_picker") {
                        gACF.val(gVal);
                        continue;
                      }

                      // DEFAULT
                      gACF.val(gVal);
                    }

                    continue;
                  }


                  // LINK
                  if (subACF.data.type === "link") {
                    const linkData = {
                      title: subValue.title || "",
                      url: subValue.url || "",
                      target: subValue.target || ""
                    };

                    console.log("🔗 Setting repeater link:", subKey, linkData);

                    subACF.val(linkData);
                    acf.doAction("render_field", $subField);
                    continue;
                  }

                  // COLOR PICKER
                  if (subACF.data.type === "color_picker") {
                    subACF.val(subValue);
                    $subField.find("input.wp-color-picker").val(subValue).trigger("change");
                    continue;
                  }

                  // WYSIWYG inside repeater
                  if (subACF.data.type === "wysiwyg") {

                    // Always set the raw ACF value first
                    subACF.val(subValue);

                    const $textarea = $subField.find("textarea");
                    const editorID = $textarea.attr("id");

                    let tries = 0;
                    const maxTries = 80; // ~10 seconds

                    const interval = setInterval(() => {
                      tries++;

                      const editor = tinyMCE?.get(editorID);

                      // CASE 1: Visual Mode - TinyMCE ready
                      if (editor && !editor.isHidden()) {
                        editor.setContent(subValue || "");
                        clearInterval(interval);
                        return;
                      }

                      // CASE 2: Text Mode - TinyMCE exists but hidden
                      if (editor && editor.isHidden()) {
                        $textarea.val(subValue || "");
                        clearInterval(interval);
                        return;
                      }

                      // CASE 3: Editor not created yet, but textarea visible (text mode)
                      if (!editor && $textarea.is(":visible")) {
                        $textarea.val(subValue || "");
                        clearInterval(interval);
                        return;
                      }

                      // CASE 4: Timeout → fallback to textarea
                      if (tries >= maxTries) {
                        $textarea.val(subValue || "");
                        clearInterval(interval);
                        return;
                      }

                    }, 100);

                    continue;
                  }




                  // Set value based on type
                  subACF.val(subValue);

                  // Render
                  acf.doAction('render_field', $subField);
                }
              });


              acf.doAction('render_field', repField.$el);
              acf.doAction('refresh', repField.$el);

              console.groupEnd();
              return;
            }






            /* 2. SIMPLE FIELDS -------------------------------*/
            const $input = $field.find('.acf-input :input').not('[id*="acfcloneindex"]').first();

            switch (type) {
              case 'text':
              case 'number':
              case 'range':
              case 'textarea':
                $input.val(fieldValue).trigger('change');
                break;

              case 'select': {
                const arr = Array.isArray(fieldValue)
                  ? fieldValue
                  : fieldValue?.includes(',') ? fieldValue.split(',') : [fieldValue];

                obj.val(arr);
                $input.val(arr).trigger('change');

                if ($input.data('select2')) {
                  $input.select2('destroy');
                  setTimeout(() => acf.doAction('select2_init', $input), 50);
                }
                break;
              }

              case 'radio':
              case 'checkbox':
                obj.val(fieldValue ? String(fieldValue).split(',') : []);
                break;

              case "color_picker":
                console.log(`🎨 Setting color ${fieldKey} →`, fieldValue);

                // Normalise
                const colorVal = fieldValue || "";

                // ACF object
                obj.val(colorVal);

                // Actual input element
                const $colorInput = $field.find('input[type="text"]');
                $colorInput.val(colorVal).trigger("change");

                // Re-init WP/ACF color picker UI
                if ($colorInput.wpColorPicker) {
                  console.log("🎨 Reinitializing WP color picker...");
                  $colorInput.wpColorPicker("color", colorVal);
                }

                // Force ACF UI refresh
                acf.doAction("render_field", $field);

                break;


              case 'wysiwyg': {

                obj.val(fieldValue);

                const $textarea = $field.find('textarea');
                const editorID = $textarea.attr('id');

                let tries = 0;
                const maxTries = 80; // 6–7 seconds

                const waitEditor = setInterval(() => {
                  tries++;

                  const ed = tinyMCE?.get(editorID);

                  // Visual mode (editor visible)
                  if (ed && !ed.isHidden()) {
                    ed.setContent(fieldValue || "");
                    clearInterval(waitEditor);
                    return;
                  }

                  // Text mode (editor hidden or disabled)
                  if (!ed || ed.isHidden()) {
                    if ($textarea.is(":visible")) {
                      $textarea.val(fieldValue || "");
                      clearInterval(waitEditor);
                      return;
                    }
                  }

                  if (tries >= maxTries) {
                    console.warn("⚠ WYSIWYG timed out, fallback applied.");
                    $textarea.val(fieldValue || "");
                    clearInterval(waitEditor);
                  }

                }, 100);

                break;
              }



              case "image":
                console.log(`🖼 IMAGE FIELD DETECTED: ${fieldKey}`, fieldValue);

                if (!fieldValue) return;

                const imageId = fieldValue.id ? parseInt(fieldValue.id) : parseInt(fieldValue);

                console.log("🔍 Using Image ID:", imageId);

                // ✔ Works for repeater, group, flex, cloned fields
                const $imageField = acf.findFields({
                  key: fieldKey,
                  parent: $parent
                });

                if (!$imageField.length) {
                  console.error("❌ Could not find image field for:", fieldKey);
                  return;
                }

                // Get field instance
                const field = acf.getField(fieldKey, $imageField);
                if (!field) {
                  console.error("❌ ACF field instance not found:", fieldKey);
                  return;
                }

                // STEP 1 — Save ID only
                field.val(imageId);

                // STEP 2 — Fetch attachment for preview
                const att = wp.media.attachment(imageId);

                att.fetch().then(() => {
                  const data = att.attributes;

                  if (!data || !data.url) return;

                  console.log("🧩 Image fetched:", data.url);

                  // STEP 3 — update preview
                  const $uploader = $imageField.find(".acf-image-uploader");
                  const $img = $uploader.find("img");

                  $img.attr("src", data.url);
                  $uploader.addClass("has-value");

                  // STEP 4 — ensure hidden input is set
                  $imageField.find('input[type="hidden"]').val(imageId);

                  console.log("✅ Repeater image saved:", imageId);
                });

                return;

              case "file": {
                console.log(`📁 Setting file field ${fieldKey} →`, fieldValue);

                if (!fieldValue) return;

                const fileId = fieldValue.id ? parseInt(fieldValue.id) : parseInt(fieldValue);

                const fileField = acf.getField(fieldKey, $field);
                if (!fileField) {
                  console.warn("⚠️ Cannot find ACF File Field object:", fieldKey);
                  return;
                }

                // 1. Apply raw ID
                fileField.val(fileId);

                // 2. Fetch attachment for preview
                const att = wp.media.attachment(fileId);

                att.fetch().then(() => {
                  const data = att.attributes;

                  const $uploader = $field.find(".acf-file-uploader");

                  // Fill hidden input
                  $uploader.find('input[type="hidden"]').val(fileId);

                  // Update Preview UI
                  $uploader.addClass("has-value");
                  $uploader.find(".file-icon").attr("src", data.icon);
                  $uploader.find(".file-info .file-title").text(data.title || data.filename);
                  $uploader.find(".file-info .file-name").text(data.filename);

                  console.log("📁 File field updated:", data);
                });

                break;
              }




              case "link":
                console.log("🔗 Setting link for:", fieldKey, fieldValue);

                // Always find field inside the parent layout
                const $linkField = $parent.find(`.acf-field[data-key="${fieldKey}"]`).first();

                if (!$linkField.length) {
                  console.warn("⚠️ Link field not found:", fieldKey);
                  break;
                }

                const acfLinkField = acf.getField($linkField);
                if (!acfLinkField) {
                  console.warn("⚠️ ACF Link object missing:", fieldKey);
                  break;
                }

                // Build link values
                const linkObj = {
                  title: fieldValue?.title || "",
                  url: fieldValue?.url || "",
                  target: fieldValue?.target || ""
                };

                // Set ACF value
                acfLinkField.val(linkObj);

                // DOM updates
                const $acfLink = $linkField.find(".acf-link");
                const $wrap = $acfLink.find(".link-wrap");

                // Hidden fields
                $acfLink.find(".input-title").val(linkObj.title);
                $acfLink.find(".input-url").val(linkObj.url);
                $acfLink.find(".input-target").val(linkObj.target);

                // Preview UI
                $wrap.find(".link-title").text(linkObj.title);
                $wrap.find(".link-url")
                  .text(linkObj.url)
                  .attr("href", linkObj.url);

                // REQUIRED → ACF preview state
                $acfLink.addClass("-value");
                $wrap.show();
                $acfLink.find('[data-name="add"]').hide();

                console.log("✅ CTA Link fully inserted:", linkObj);
                break;


              case "post_object":
              case "relationship": {
                const ids = Array.isArray(fieldValue)
                  ? fieldValue.map(v => parseInt(v))
                  : [parseInt(fieldValue)];

                console.log(`🔗 Applying post_object/relationship → ${fieldKey}:`, ids);

                const $select = $field.find("select");
                const $hidden = $field.find('input[type="hidden"]');

                // Set raw ACF value
                obj.val(ids);
                $hidden.val(ids.join(','));
                $select.empty(); // Clear old options

                // Fetch all titles first
                const titles = {};

                await Promise.all(
                  ids.map(id =>
                    $.ajax({
                      url: `${window.location.origin}/wp-json/wp/v2/posts/${id}`,
                      method: "GET"
                    })
                      .done(post => {
                        titles[id] = post?.title?.rendered || `Post #${id}`;
                      })
                      .fail(() => {
                        titles[id] = `Post #${id}`;
                      })
                  )
                );

                console.log("📄 Loaded post titles:", titles);

                // Insert <option> elements
                Object.entries(titles).forEach(([id, title]) => {
                  $select.append(`<option value="${id}" selected="selected">${title}</option>`);
                });

                // Wait for Select2 AJAX init then update selection
                const syncSelect2 = setInterval(() => {
                  const inst = $select.data("select2");

                  if (!inst) return; // Not ready yet

                  clearInterval(syncSelect2);

                  console.log("⚡ Select2 ready — forcing correct label…");

                  // Build final select2 data
                  const select2Data = Object.entries(titles).map(([id, text]) => ({
                    id,
                    text
                  }));

                  // Force Select2 to use correct title
                  inst.dataAdapter.current = function (cb) {
                    cb(select2Data);
                  };

                  $select.trigger("change.select2");

                  console.log("✅ Select2 label fixed:", select2Data);
                }, 50);

                break;
              }

              case "true_false": {
                console.log(`🔘 Setting true_false field ${fieldKey} →`, fieldValue);

                const checked = fieldValue == 1 || fieldValue === true || fieldValue === "1";

                // Set raw ACF value
                obj.val(checked ? 1 : 0);

                // Update checkbox UI
                const $checkbox = $field.find('input[type="checkbox"]');
                $checkbox.prop("checked", checked).trigger("change");

                // Force ACF toggle UI refresh
                setTimeout(() => {
                  acf.doAction('render_field', $field);
                  acf.doAction('refresh', $field);
                }, 50);

                break;
              }









              default:
                $input.val(fieldValue).trigger('change');
            }

            acf.doAction('render_field', $field);
          }

          /* -------------------------------------------
             Layout insertion
          --------------------------------------------*/
          const layoutsArr = mod.data;

          for (const layout of layoutsArr) {

            const $layoutEl = field.add({
              layout: layout.acf_fc_layout,
              before: false
            });

            console.log(`🧩 Inserted layout: ${layout.acf_fc_layout}`);

            // wait for fields to appear
            for (let i = 0; i < 40; i++) {
              if ($layoutEl.find('.acf-field[data-key]').length) break;
              await new Promise(r => setTimeout(r, 50));
            }

            for (const [fieldKey, val] of Object.entries(layout)) {
              // ✅ skip internal ACF flags
              if (
                fieldKey === 'acf_fc_layout' ||
                fieldKey === 'acf_fc_layout_disabled' ||
                fieldKey === 'acf_fc_layout_custom_label'
              ) {
                continue;
              }

              await applyField($layoutEl, fieldKey, val);
            }

            acf.doAction('refresh', $layoutEl);
          }

          toast('✅ Module inserted');
          console.log('✅ Module successfully inserted');
        } finally {
          // 🔥 Always restore button
          $btn.prop("disabled", false);
          $btn.text(oldText);
        }
        $modal.remove();

      });
    } finally {
      // 🔥 Re-enable Insert button
      $btnm.prop("disabled", false);
      $btnm.text(oldText);
    }
  });



  // Select all
  $("#acfml-select-all").on("change", function () {
    $(".acfml-select-row").prop("checked", this.checked);
  });

  // Delete Selected
  $(".acfml-delete-selected").on("click", function () {
    const ids = $(".acfml-select-row:checked").map((i, el) => {
      return $(el).closest("tr").data("id");
    }).get();

    if (!ids.length) return alert("No modules selected.");
    if (!confirm("Delete selected modules?")) return;

    fetch(ajaxurl, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "action=acfml_delete_bulk&ids=" + JSON.stringify(ids)
    })
      .then(r => r.json())
      .then(res => {
        if (res.ok) location.reload();
      });
  });

  // Export Selected
  $(".acfml-export-selected").on("click", function () {
    const ids = $(".acfml-select-row:checked").map((i, el) => {
      return $(el).closest("tr").data("id");
    }).get();

    if (!ids.length) return alert("No modules selected.");

    window.location = ajaxurl + "?action=acfml_export_bulk&ids=" + JSON.stringify(ids);
  });

  // Export All
  $(".acfml-export-all").on("click", function () {
    window.location = ajaxurl + "?action=acfml_export_all";
  });

  // Import
  $("#acfml-import-file").on("change", function () {
    const file = this.files[0];
    if (!file) return;

    const form = new FormData();
    form.append("file", file);
    form.append("action", "acfml_import_modules");

    fetch(ajaxurl, {
      method: "POST",
      body: form
    })
      .then(r => r.json())
      .then(res => {
        if (res.ok) location.reload();
      });
  });

  function enableAfcSearch() {
    // Wait until ACF opens the layout popup
    $(document).on("click", "[data-event='add-layout']", function () {

      setTimeout(function () {

        const $popup = $(".acf-fc-popup:visible");

        if (!$popup.length) return;

        // Prevent multiple injections
        if ($popup.find(".acf-fc-search").length) return;

        // Inject search bar
        $popup.prepend(`
          <div class="acf-fc-search-wrap" style="padding:8px;">
            <input type="text" class="acf-fc-search" placeholder="Search module…" 
              style="width:100%; padding:6px 10px; border:1px solid #ccd0d4; border-radius:4px;">
          </div>
        `);

        // Bind filter logic
        const $items = $popup.find("a[data-layout]");

        $popup.find(".acf-fc-search").on("keyup", function () {
          const term = $(this).val().toLowerCase();

          $items.each(function () {
            const text = $(this).text().trim().toLowerCase();
            $(this).toggle(text.indexOf(term) !== -1);
          });
        });

      }, 50);
    });
  }

  $(document).ready(enableAfcSearch);
				
	$(document).on('click', '[data-name="add-layout"]', function () {

		setTimeout(() => {

			const $menu = $('.acf-fc-popup:visible');
			if (!$menu.length) return;

			// Prevent duplicate search
			if ($menu.find('.acf-layout-search').length) return;

			// Insert search input
			$menu.prepend(`
				<div style="padding:8px;">
					<input 
						type="text" 
						class="acf-layout-search" 
						placeholder="Search layouts..." 
						style="width:100%; padding:6px; border:1px solid #ccc;" 
					/>
				</div>
			`);

			// 🔥 MUST select <li> not <a> to avoid gaps
			const $items = $menu.find('li:has(a[data-layout])');

			// Prevent popup close when typing
			$menu.on('mousedown click keyup', '.acf-layout-search', function (e) {
				e.stopPropagation();
			});

			// LIVE SEARCH
			$menu.on('input', '.acf-layout-search', function () {
				const q = $(this).val().toLowerCase();

				$items.each(function () {
					const txt = $(this).text().toLowerCase();
					$(this).toggle(txt.includes(q));
				});
			});

		}, 50);

	});


})(jQuery);
