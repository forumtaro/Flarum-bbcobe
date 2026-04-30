app.initializers.add('olleksi-bbcodes', function() {
    
    // Реєструємо налаштування
    app.extensionData.for('olleksi-bbcodes')
        .registerSetting({
            setting: 'olleksi-bbcodes.hide_bold',
            type: 'boolean',
            label: 'Приховати жирний (bold)'
        })
        .registerSetting({
            setting: 'olleksi-bbcodes.hide_italic',
            type: 'boolean',
            label: 'Приховати курсив (italic)'
        })
        .registerSetting({
            setting: 'olleksi-bbcodes.hide_underline',
            type: 'boolean',
            label: 'Приховати підкреслення (underline)'
        })
        .registerSetting({
            setting: 'olleksi-bbcodes.hide_link',
            type: 'boolean',
            label: 'Приховати посилання (link)'
        })
        .registerSetting({
            setting: 'olleksi-bbcodes.hide_image',
            type: 'boolean',
            label: 'Приховати зображення (image)'
        })
        .registerSetting({
            setting: 'olleksi-bbcodes.hide_code',
            type: 'boolean',
            label: 'Приховати код (code)'
        })
        .registerSetting({
            setting: 'olleksi-bbcodes.hide_quote',
            type: 'boolean',
            label: 'Приховати цитату (quote)'
        })
        .registerSetting({
            setting: 'olleksi-bbcodes.hide_strike',
            type: 'boolean',
            label: 'Приховати закреслення (strike)'
        })
        .registerSetting({
            setting: 'olleksi-bbcodes.hide_header',
            type: 'boolean',
            label: 'Приховати заголовок (header)'
        })
        .registerSetting({
            setting: 'olleksi-bbcodes.hide_list',
            type: 'boolean',
            label: 'Приховати списки (list)'
        })
        .registerSetting({
            setting: 'olleksi-bbcodes.hide_spoiler',
            type: 'boolean',
            label: 'Приховати спойлер (spoiler)'
        })
        .registerSetting({
            setting: 'olleksi-bbcodes.hide_mention',
            type: 'boolean',
            label: 'Приховати згадування (mention)'
        })
        .registerSetting({
            setting: 'olleksi-bbcodes.hide_preview',
            type: 'boolean',
            label: 'Приховати попередній перегляд (preview)'
        });

    // Додаємо UI для кастомних BB-кодів
    app.extensionData.for('olleksi-bbcodes').done(function() {
        setTimeout(addBbcodesUI, 500);
    });
});

var bbcodes = [];

function addBbcodesUI() {
    // Отримуємо збережені BB-коди
    var saved = app.data.settings['olleksi-bbcodes.custom_bbcodes'];
    try {
        bbcodes = JSON.parse(saved || '[]');
    } catch(e) {
        bbcodes = [];
    }
    
    var settingsSection = document.querySelector('.ExtensionPage-settings');
    if (!settingsSection) {
        setTimeout(addBbcodesUI, 300);
        return;
    }
    
    if (document.getElementById('olleksi-bbcode-ui')) return;
    
    var ui = document.createElement('div');
    ui.id = 'olleksi-bbcode-ui';
    ui.innerHTML = '' +
        '<div style="margin-top:24px;border-top:2px solid #e0e0e0;padding-top:16px">' +
            '<h3 style="font-size:15px;font-weight:600;margin-bottom:12px">Кастомні BB-коди</h3>' +
            '<p id="olleksi-msg" style="display:none;font-size:13px;margin-bottom:8px"></p>' +
            '<div id="olleksi-bbcode-list" style="margin-bottom:16px"></div>' +
            '<div style="background:#f8f8f8;border-radius:6px;padding:14px">' +
                '<p id="olleksi-form-title" style="font-size:14px;font-weight:600;margin-bottom:10px">Новий BB-код</p>' +
                '<input type="hidden" id="olleksi-f-idx" value="-1">' +
                '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">' +
                    '<div><label style="font-size:12px;color:#555">Назва кнопки</label>' +
                    '<input id="olleksi-f-name" class="FormControl" placeholder="напр. Спойлер" style="width:100%"></div>' +
                    '<div><label style="font-size:12px;color:#555">FA іконка</label>' +
                    '<input id="olleksi-f-icon" class="FormControl" placeholder="fa-star" style="width:100%"></div>' +
                '</div>' +
                '<div style="margin-bottom:8px"><label style="font-size:12px;color:#555">Підказка (tooltip)</label>' +
                '<input id="olleksi-f-tooltip" class="FormControl" placeholder="Текст при наведенні" style="width:100%"></div>' +
                '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px">' +
                    '<div><label style="font-size:12px;color:#555">Відкриваючий тег</label>' +
                    '<input id="olleksi-f-open" class="FormControl" placeholder="[spoiler]" style="width:100%"></div>' +
                    '<div><label style="font-size:12px;color:#555">Закриваючий тег</label>' +
                    '<input id="olleksi-f-close" class="FormControl" placeholder="[/spoiler]" style="width:100%"></div>' +
                '</div>' +
                '<div style="display:flex;gap:8px">' +
                    '<button class="Button Button--primary" id="olleksi-save-btn">Зберегти</button>' +
                    '<button class="Button" id="olleksi-reset-btn">Скинути форму</button>' +
                '</div>' +
            '</div>' +
        '</div>';
    
    settingsSection.appendChild(ui);
    render();
    
    // Обробники подій
    document.getElementById('olleksi-save-btn').addEventListener('click', function() {
        var idx = parseInt(document.getElementById('olleksi-f-idx').value);
        var entry = {
            name: document.getElementById('olleksi-f-name').value.trim(),
            icon: document.getElementById('olleksi-f-icon').value.trim() || 'fa-star',
            tooltip: document.getElementById('olleksi-f-tooltip').value.trim(),
            open: document.getElementById('olleksi-f-open').value,
            close: document.getElementById('olleksi-f-close').value,
            visible: true
        };
        
        if (!entry.name) {
            showMsg('Вкажіть назву кнопки', true);
            return;
        }
        
        if (idx >= 0 && idx < bbcodes.length) {
            entry.visible = bbcodes[idx].visible;
            bbcodes[idx] = entry;
        } else {
            bbcodes.push(entry);
        }
        
        save('Збережено!');
        document.getElementById('olleksi-reset-btn').click();
    });
    
    document.getElementById('olleksi-reset-btn').addEventListener('click', function() {
        document.getElementById('olleksi-f-idx').value = '-1';
        document.getElementById('olleksi-f-name').value = '';
        document.getElementById('olleksi-f-icon').value = '';
        document.getElementById('olleksi-f-tooltip').value = '';
        document.getElementById('olleksi-f-open').value = '';
        document.getElementById('olleksi-f-close').value = '';
        document.getElementById('olleksi-form-title').textContent = 'Новий BB-код';
    });
}

function save(successMsg) {
    app.request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/olleksi-bbcodes',
        body: { bbcodes: bbcodes }
    }).then(function(data) {
        bbcodes = data.bbcodes;
        render();
        showMsg(successMsg || 'Збережено!', false);
    }).catch(function(err) {
        console.error('Save error:', err);
        showMsg('Помилка збереження: ' + (err.message || 'Невідома помилка'), true);
    });
}

function showMsg(text, isErr) {
    var el = document.getElementById('olleksi-msg');
    if (!el) return;
    el.textContent = text;
    el.style.color = isErr ? '#c0392b' : '#27ae60';
    el.style.display = 'block';
    setTimeout(function() { el.style.display = 'none'; }, 3000);
}

function escHtml(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function render() {
    var wrap = document.getElementById('olleksi-bbcode-list');
    if (!wrap) return;
    
    var rows = bbcodes.map(function(bb, i) {
        return '<div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid #eee">' +
            '<i class="fas ' + escHtml(bb.icon) + '" style="width:20px;text-align:center"></i>' +
            '<span style="flex:1;font-size:13px">' + escHtml(bb.name) + '</span>' +
            '<span style="font-size:11px;color:#888;flex:2">' + escHtml(bb.tooltip) + '</span>' +
            '<button class="Button Button--primary" style="font-size:11px;padding:2px 8px" onclick="window._olleksiEdit(' + i + ')">Ред.</button>' +
            '<button class="Button" style="font-size:11px;padding:2px 8px" onclick="window._olleksiToggle(' + i + ')">' + (bb.visible ? 'Видно' : 'Прих.') + '</button>' +
            '<button class="Button Button--danger" style="font-size:11px;padding:2px 8px" onclick="window._olleksiDelete(' + i + ')">X</button>' +
        '</div>';
    }).join('');
    
    wrap.innerHTML = rows || '<p style="color:#999;font-size:13px">Немає кастомних BB-кодів</p>';
}

// Глобальні функції для кнопок у рендері
window._olleksiEdit = function(i) {
    var bb = bbcodes[i];
    document.getElementById('olleksi-f-name').value = bb.name;
    document.getElementById('olleksi-f-icon').value = bb.icon;
    document.getElementById('olleksi-f-tooltip').value = bb.tooltip;
    document.getElementById('olleksi-f-open').value = bb.open;
    document.getElementById('olleksi-f-close').value = bb.close;
    document.getElementById('olleksi-f-idx').value = i;
    document.getElementById('olleksi-form-title').textContent = 'Редагувати BB-код';
};

window._olleksiToggle = function(i) {
    bbcodes[i].visible = !bbcodes[i].visible;
    save('Видимість змінено');
};

window._olleksiDelete = function(i) {
    if (!confirm('Видалити "' + bbcodes[i].name + '"?')) return;
    bbcodes.splice(i, 1);
    save('Видалено');
};
