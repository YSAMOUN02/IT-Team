{{--
    Rich-text editor (TinyMCE 7, open-source GPL build) for lessons, articles,
    pages and course descriptions. The editing area loads the site's own CSS,
    so what you write looks the way students will see it.
--}}
@once
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/tinymce@7.6.1/tinymce.min.js" referrerpolicy="origin"></script>
        <script>
        (() => {
            const csrf = () => document.querySelector('meta[name=csrf-token]').content;
            const send = (url, file, name) => {
                const data = new FormData();
                data.append('file', file, name || file.name);
                return fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' }, body: data })
                    .then(async (response) => {
                        const json = await response.json().catch(() => ({}));
                        if (!response.ok) {
                            throw new Error((json.errors && Object.values(json.errors)[0][0]) || json.message || 'Upload failed (' + response.status + ')');
                        }
                        return json;
                    });
            };

            // Icons for the lesson tools (24 × 24, filled like TinyMCE's own).
            const icons = {
                'lesson-blocks': '<svg width="24" height="24"><path d="M4 4h7v7H4V4Zm9 0h7v7h-7V4ZM4 13h7v7H4v-7Zm12 0h2v3h3v2h-3v3h-2v-3h-3v-2h3v-3Z"/></svg>',
                'word-doc': '<svg width="24" height="24"><path fill-rule="evenodd" d="M6 2h8l5 5v14a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1Zm7 1.5V8h4.5L13 3.5ZM7.3 11h1.6l1.1 4.4 1.2-4.4h1.6l1.2 4.4 1.1-4.4h1.6l-2 7h-1.5L12 13.7 10.8 18H9.3l-2-7Z"/></svg>',
                'tip': '<svg width="24" height="24"><path fill-rule="evenodd" d="M12 2a7 7 0 0 1 4 12.7V17a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1v-2.3A7 7 0 0 1 12 2Zm0 2a5 5 0 0 0-2.6 9.3l.6.3V16h4v-2.4l.6-.3A5 5 0 0 0 12 4ZM9 20h6v2H9v-2Z"/></svg>',
                'steps': '<svg width="24" height="24"><path d="M3.5 4h4v4h-4V4Zm6 1h11v2h-11V5Zm-6 5h4v4h-4v-4Zm6 1h11v2h-11v-2Zm-6 5h4v4h-4v-4Zm6 1h11v2h-11v-2Z"/></svg>',
                'exercise': '<svg width="24" height="24"><path fill-rule="evenodd" d="M9 2h6v2h3a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h3V2Zm2 2v1h2V4h-2ZM7 6v14h10V6H7Zm8.3 4.3 1.4 1.4-5.2 5.2-3.2-3.2 1.4-1.4 1.8 1.8 3.8-3.8Z"/></svg>',
                'keyboard': '<svg width="24" height="24"><path fill-rule="evenodd" d="M3 6h18a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Zm1 2v8h16V8H4Zm1 1h2v2H5V9Zm3 0h2v2H8V9Zm3 0h2v2h-2V9Zm3 0h2v2h-2V9Zm3 0h2v2h-2V9ZM7 13h10v2H7v-2Z"/></svg>',
                'formula': '<svg width="24" height="24"><path d="M6 4h12v3.5h-2V6H9.4l4.3 6-4.3 6H16v-1.5h2V20H6v-1.6l5.2-6.4L6 5.6V4Z"/></svg>',
                'shots': '<svg width="24" height="24"><path fill-rule="evenodd" d="M2 6h9v12H2V6Zm2 2v8h5V8H4Zm9-2h9v12h-9V6Zm2 2v8h5V8h-5Z"/></svg>',
            };

            // Ready-made lesson blocks. The text is Khmer, the language students read; change it after inserting.
            const blocks = [
                { text: 'Tip box', icon: 'tip', html: '<div class="callout callout-tip"><p class="callout-title">គន្លឹះ</p><p>សរសេរគន្លឹះនៅទីនេះ…</p></div>' },
                { text: 'Note box', icon: 'info', html: '<div class="callout callout-note"><p class="callout-title">ចំណាំ</p><p>សរសេរចំណាំនៅទីនេះ…</p></div>' },
                { text: 'Warning box', icon: 'warning', html: '<div class="callout callout-warning"><p class="callout-title">ប្រយ័ត្ន</p><p>សរសេរការព្រមាននៅទីនេះ…</p></div>' },
                '|',
                { text: 'Numbered steps', icon: 'steps', html: '<ol class="steps"><li>ជំហានទី ១…</li><li>ជំហានទី ២…</li><li>ជំហានទី ៣…</li></ol>' },
                { text: 'Exercise', icon: 'exercise', html: '<div class="exercise"><p class="exercise-title">លំហាត់អនុវត្ត</p><ol><li>…</li><li>…</li></ol></div>' },
                { text: 'Answer (hidden until clicked)', icon: 'accordion', html: '<details class="mce-accordion answer" open><summary class="mce-accordion-summary">មើលចម្លើយ</summary><p>សរសេរចម្លើយនៅទីនេះ…</p></details>' },
                '|',
                { text: 'Two screenshots side by side', icon: 'shots', html: '<div class="shot-pair"><p>បិទភ្ជាប់រូបភាពទី ១ នៅទីនេះ</p><p>បិទភ្ជាប់រូបភាពទី ២ នៅទីនេះ</p></div>' },
                { text: 'Keyboard shortcut…', icon: 'keyboard', dialog: 'keys' },
                { text: 'Excel formula…', icon: 'formula', dialog: 'formula' },
                { text: 'Divider line', icon: 'horizontal-rule', html: '<hr>' },
            ];

            const askAndInsert = (editor, kind) => editor.windowManager.open({
                title: kind === 'keys' ? 'Keyboard shortcut' : 'Excel formula',
                body: { type: 'panel', items: [kind === 'keys'
                    ? { type: 'input', name: 'value', label: 'Keys, joined with +', placeholder: 'Ctrl + Shift + L' }
                    : { type: 'input', name: 'value', label: 'Formula', placeholder: '=SUMIFS(L:L, G:G, "Phnom Penh")' }] },
                buttons: [{ type: 'cancel', text: 'Cancel' }, { type: 'submit', text: 'Insert', primary: true }],
                onSubmit: (api) => {
                    const value = api.getData().value.trim();
                    if (value && kind === 'keys') {
                        editor.insertContent(value.split('+').map((key) => key.trim()).filter(Boolean)
                            .map((key) => '<kbd>' + editor.dom.encode(key) + '</kbd>').join(' + ') + '&nbsp;');
                    } else if (value) {
                        editor.insertContent('<p class="formula"><code>' + editor.dom.encode(value) + '</code></p>');
                    }
                    api.close();
                },
            });

            const importWord = (editor) => {
                const input = document.createElement('input');
                input.type = 'file';
                input.accept = '.docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document';
                input.addEventListener('change', () => {
                    const file = input.files[0];
                    if (!file) return;
                    editor.setProgressState(true);
                    send(@json(route('admin.uploads.word')), file)
                        .then((json) => {
                            editor.insertContent(json.html);
                            editor.notificationManager.open({ type: 'success', timeout: 6000,
                                text: 'Imported “' + file.name + '”: ' + json.paragraphs + ' paragraphs, ' + json.images + ' pictures. Check headings and lists before saving.' });
                        })
                        .catch((error) => editor.notificationManager.open({ type: 'error', text: error.message }))
                        .finally(() => editor.setProgressState(false));
                });
                input.click();
            };

            tinymce.init({
                selector: 'textarea[data-editor]',
                license_key: 'gpl',
                branding: false,
                promotion: false,
                help_accessibility: false,

                plugins: 'accordion advlist anchor autolink autoresize autosave charmap code codesample fullscreen help image link lists media preview quickbars searchreplace table visualblocks wordcount',
                menubar: 'file edit view insert format table tools help',
                toolbar: 'undo redo | blocks | bold italic underline forecolor backcolor | lessonblocks importword | bullist numlist outdent indent | alignleft aligncenter alignright | link image media table | removeformat | searchreplace preview fullscreen code',
                toolbar_mode: 'wrap', // every tool visible, on two rows when the box is narrow
                toolbar_sticky: true,
                toolbar_sticky_offset: 65,
                min_height: 480,
                autoresize_bottom_margin: 40,
                autoresize_overflow_padding: 28, // the plugin sets the side padding itself
                contextmenu: 'link image table',
                browser_spellcheck: true,
                placeholder: 'Write here… Paste screenshots straight in (Win + Shift + S, then Ctrl + V), or click “Word” to import a .docx manual.',

                // Looks like the live site
                content_css: [@json(asset('css/site.css').'?v='.filemtime(public_path('css/site.css')))],
                body_class: 'entry lesson-body',
                content_style: "@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Kantumruy+Pro:wght@400;500;600;700&display=swap'); body { padding-top: 22px; padding-bottom: 40px; max-width: 880px; margin: 0 auto; background: #fff; } img { max-width: 100%; height: auto; }",

                block_formats: 'Paragraph=p; Heading 2=h2; Heading 3=h3; Heading 4=h4; Quote=blockquote; Preformatted=pre',
                style_formats_merge: true,
                style_formats: [
                    { title: 'Boxes', items: [
                        { title: 'Tip box', block: 'div', classes: 'callout callout-tip', wrapper: true },
                        { title: 'Note box', block: 'div', classes: 'callout callout-note', wrapper: true },
                        { title: 'Warning box', block: 'div', classes: 'callout callout-warning', wrapper: true },
                        { title: 'Exercise box', block: 'div', classes: 'exercise', wrapper: true },
                    ] },
                    { title: 'Text', items: [
                        { title: 'Keyboard key', inline: 'kbd' },
                        { title: 'Highlight', inline: 'mark' },
                        { title: 'Excel formula / code', inline: 'code' },
                        { title: 'Lead paragraph', block: 'p', classes: 'lead' },
                    ] },
                    { title: 'Lists', items: [
                        { title: 'Numbered steps', selector: 'ol', classes: 'steps' },
                    ] },
                ],
                color_map: ['343090', 'Brand', '5F59F7', 'Brand light', '0F9D58', 'Green', 'C77700', 'Amber', 'D93025', 'Red', '111827', 'Ink', '5C6B8D', 'Grey', 'FFF1B8', 'Yellow highlight'],
                text_patterns: [
                    { start: '#', format: 'h2', trigger: 'space' },
                    { start: '##', format: 'h3', trigger: 'space' },
                    { start: '###', format: 'h4', trigger: 'space' },
                    { start: '*', cmd: 'InsertUnorderedList', trigger: 'space' },
                    { start: '-', cmd: 'InsertUnorderedList', trigger: 'space' },
                    { start: '1.', cmd: 'InsertOrderedList', trigger: 'space' },
                    { start: '>', cmd: 'mceBlockQuote', trigger: 'space' },
                    { start: '**', end: '**', format: 'bold' },
                    { start: '---', replacement: '<hr>' },
                ],
                end_container_on_empty_block: 'div,blockquote',
                details_initial_state: 'expanded',
                details_serialized_state: 'collapsed',

                // Images: pick, drop or paste; uploads happen on their own and big screenshots are scaled down
                image_caption: true,
                image_dimensions: false,
                image_title: false,
                automatic_uploads: true,
                paste_data_images: true,
                images_file_types: 'jpg,jpeg,png,gif,webp',
                images_upload_handler: (blobInfo) => send(@json(route('admin.uploads.store')), blobInfo.blob(), blobInfo.filename()).then((json) => json.location),
                relative_urls: false,
                remove_script_host: true,
                convert_urls: true,
                extended_valid_elements: 'iframe[src|width|height|frameborder|allow|allowfullscreen|title|referrerpolicy|loading]',
                table_default_attributes: {},
                table_default_styles: { width: '100%' },
                table_header_type: 'sectionCells',

                quickbars_selection_toolbar: 'bold italic underline | forecolor backcolor | quicklink blocks',
                quickbars_insert_toolbar: 'quickimage quicktable lessonblocks',
                quickbars_image_toolbar: 'alignleft aligncenter alignright | image',

                autosave_ask_before_unload: false,
                autosave_interval: '15s',
                autosave_retention: '240m',
                autosave_restore_when_empty: false,

                setup: (editor) => {
                    Object.entries(icons).forEach(([name, svg]) => editor.ui.registry.addIcon(name, svg));

                    const items = () => blocks.map((block) => block === '|' ? { type: 'separator' } : {
                        type: 'menuitem', text: block.text, icon: block.icon,
                        onAction: () => block.dialog ? askAndInsert(editor, block.dialog) : editor.insertContent(block.html),
                    });
                    editor.ui.registry.addMenuButton('lessonblocks', { icon: 'lesson-blocks', text: 'Insert block', tooltip: 'Tip, note, steps, exercise, answer, shortcut, formula…', fetch: (done) => done(items()) });
                    editor.ui.registry.addNestedMenuItem('lessonblocks', { icon: 'lesson-blocks', text: 'Lesson block', getSubmenuItems: items });
                    editor.ui.registry.addButton('importword', { icon: 'word-doc', text: 'Word', tooltip: 'Import a Word document (.docx) with its pictures', onAction: () => importWord(editor) });
                    editor.ui.registry.addMenuItem('importword', { icon: 'word-doc', text: 'Import Word document…', onAction: () => importWord(editor) });
                },
                menu: {
                    file: { title: 'File', items: 'importword | restoredraft | preview | print' },
                    insert: { title: 'Insert', items: 'lessonblocks | image link media inserttable codesample | accordion charmap anchor hr' },
                },
            });
        })();
        </script>
    @endpush
@endonce
