<?php

namespace Olleksi\Bbcodes;

use Flarum\Extend;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

// ─── Контролер для збереження BB-кодів з адмін панелі ───────────────────────
class SaveBbcodesHandler implements RequestHandlerInterface
{
    private SettingsRepositoryInterface $settings;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        
        if (!$actor->isAdmin()) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        $body = $request->getParsedBody();
        $bbcodes = Arr::get($body, 'bbcodes', []);

        $clean = array_values(array_filter(array_map(function ($item) {
            if (empty($item['name']) || strlen(trim($item['name'])) === 0) {
                return null;
            }

            $name = preg_replace('/[^a-zA-Zа-яА-ЯіІїЇєЄ0-9\s\-_]/u', '', trim($item['name']));
            if (empty($name) || strlen($name) > 32) {
                return null;
            }

            $icon = $this->sanitizeIcon($item['icon'] ?? 'fa-star');
            $tooltip = $this->sanitizeText($item['tooltip'] ?? '', 64);
            $open = $this->sanitizeText($item['open'] ?? '', 128);
            $close = $this->sanitizeText($item['close'] ?? '', 128);

            if ($this->containsDangerousContent($open) || $this->containsDangerousContent($close)) {
                return null;
            }

            return [
                'name'    => $name,
                'icon'    => $icon,
                'tooltip' => $tooltip,
                'open'    => $open,
                'close'   => $close,
                'visible' => (bool)($item['visible'] ?? true),
            ];
        }, $bbcodes)));

        if (count($clean) > 50) {
            $clean = array_slice($clean, 0, 50);
        }

        $jsonData = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT);
        if ($jsonData === false || json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse(['error' => 'Failed to encode data'], 500);
        }

        if (strlen($jsonData) > 65535) {
            return new JsonResponse(['error' => 'Data too large'], 413);
        }

        $this->settings->set('forumtaro-bbcodes.custom_bbcodes', $jsonData);

        return new JsonResponse([
            'success' => true, 
            'bbcodes' => $clean,
            'count' => count($clean)
        ]);
    }

    private function sanitizeIcon(string $icon): string
    {
        $icon = preg_replace('/[^a-zA-Z0-9\-]/', '', trim($icon));
        
        $allowedPrefixes = ['fa-', 'fas-', 'far-', 'fal-', 'fab-'];
        
        $hasValidPrefix = false;
        foreach ($allowedPrefixes as $prefix) {
            if (strpos($icon, $prefix) === 0) {
                $hasValidPrefix = true;
                break;
            }
        }
        
        if (!$hasValidPrefix && !empty($icon)) {
            $icon = 'fa-' . $icon;
        }
        
        $icon = substr($icon, 0, 32);
        return $icon ?: 'fa-star';
    }

    private function sanitizeText(string $text, int $maxLength): string
    {
        $text = str_replace("\0", '', $text);
        $text = htmlspecialchars(trim($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return substr($text, 0, $maxLength);
    }

    private function containsDangerousContent(string $text): bool
    {
        $dangerous = [
            'javascript:', 'data:', 'vbscript:',
            '<script', '</script', 'onerror=', 'onload=',
            'onclick=', 'eval(', 'expression(',
            '<iframe', '</iframe', '<object', '</object',
            '<embed', '</embed'
        ];
        
        $lowerText = strtolower($text);
        foreach ($dangerous as $pattern) {
            if (strpos($lowerText, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
}

// ─── Хелпер для отримання та валідації налаштувань ─────────────────────────
function getValidatedSettings(SettingsRepositoryInterface $settings): array
{
    $rawBbcodes = $settings->get('forumtaro-bbcodes.custom_bbcodes', '[]');
    $decoded = json_decode($rawBbcodes, true);
    
    if (!is_array($decoded)) {
        $decoded = [];
    }
    
    $validated = array_filter($decoded, function($item) {
        return is_array($item) && 
               isset($item['name']) && 
               !empty(trim($item['name']));
    });

    return [
        'custom_bbcodes' => array_values($validated),
        'hide_bold' => (bool)$settings->get('forumtaro-bbcodes.hide_bold', false),
        'hide_italic' => (bool)$settings->get('forumtaro-bbcodes.hide_italic', false),
        'hide_underline' => (bool)$settings->get('forumtaro-bbcodes.hide_underline', false),
        'hide_link' => (bool)$settings->get('forumtaro-bbcodes.hide_link', false),
        'hide_image' => (bool)$settings->get('forumtaro-bbcodes.hide_image', false),
        'hide_code' => (bool)$settings->get('forumtaro-bbcodes.hide_code', false),
        'hide_quote' => (bool)$settings->get('forumtaro-bbcodes.hide_quote', false),
        'hide_strike' => (bool)$settings->get('forumtaro-bbcodes.hide_strike', false),
        'hide_header' => (bool)$settings->get('forumtaro-bbcodes.hide_header', false),
        'hide_list' => (bool)$settings->get('forumtaro-bbcodes.hide_list', false),
        'hide_spoiler' => (bool)$settings->get('forumtaro-bbcodes.hide_spoiler', false),
        'hide_mention' => (bool)$settings->get('forumtaro-bbcodes.hide_mention', false),
        'hide_preview' => (bool)$settings->get('forumtaro-bbcodes.hide_preview', false),
    ];
}

// ─── Генерація динамічного CSS для миттєвого приховування кнопок ─────────
function generateDynamicCss(array $validated): string
{
    $css = '';
    
    $iconMap = [
        'hide_bold' => 'fa-bold',
        'hide_italic' => 'fa-italic',
        'hide_underline' => 'fa-underline',
        'hide_link' => 'fa-link',
        'hide_image' => 'fa-image',
        'hide_code' => 'fa-code',
        'hide_quote' => 'fa-quote-left',
        'hide_strike' => 'fa-strikethrough',
        'hide_header' => 'fa-heading',
        'hide_list' => ['fa-list-ul', 'fa-list-ol'],
        'hide_spoiler' => 'fa-exclamation-triangle',
        'hide_mention' => 'fa-at',
        'hide_preview' => 'fa-eye'
    ];
    
    foreach ($validated as $key => $value) {
        if (strpos($key, 'hide_') === 0 && $value && isset($iconMap[$key])) {
            $icons = (array)$iconMap[$key];
            foreach ($icons as $icon) {
                $css .= ".TextEditor-controls .Button--icon:has(.{$icon}) { display: none !important; }\n";
            }
        }
    }
    
    $css .= "\n.TextEditor-controls[data-olleksi-processed] .Button--icon:not([data-olleksi-hidden]) { display: inline-flex !important; }\n";
    
    return $css;
}

// ─── CSS стилі ──────────────────────────────────────────────────────────────
function getStyles(): string
{
    return '
    .TextEditor-controls .Button--icon {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: 36px !important;
        height: 36px !important;
        padding: 0 !important;
        vertical-align: middle !important;
    }
    
    .TextEditor-controls .Button--icon .icon {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: auto !important;
        height: auto !important;
        margin: 0 !important;
        font-size: 16px !important;
        line-height: 1 !important;
    }
    
    .gallery-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 99999;
        justify-content: center;
        align-items: center;
    }
    
    .gallery-modal-content {
        background: white;
        width: 90%;
        max-width: 1200px;
        height: 80%;
        max-height: 800px;
        border-radius: 20px;
        display: flex;
        flex-direction: column;
        box-shadow: 0 5px 20px rgba(0,0,0,0.3);
    }
    
    .gallery-modal-header {
        padding: 15px;
        border-bottom: 1px solid #ddd;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .gallery-modal-header h3 {
        margin: 0;
        font-size: 18px;
    }
    
    
    
    .gallery-close-btn {
    background: #e4e8f6;
    color: #667199;
    border: none;
    padding: 10px 25px;
    border-radius: 25px;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-family: Georgia, serif;
} 
    
    .gallery-modal-footer {
    padding: 10px 20px;
    border-top: 1px solid #eee;
    display: flex;
    justify-content: center; /* Центрує кнопку по горизонталі */
    align-items: center;     /* Центрує кнопку по вертикалі */
    background: #f8f8f8;
}
    
    
    
    .gallery-close {
        background: none;
        border: none;
        font-size: 28px;
        cursor: pointer;
        width: 35px;
        height: 35px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background-color 0.2s;
    }
    
    .gallery-close:hover {
        background: #f0f0f0;
    }
    
    .gallery-modal-body {
        flex: 1;
        
        padding: 0;
        -webkit-overflow-scrolling: touch;
    }
    
    .gallery-modal-body iframe {
        border-radius: 20px;
        width: 100%;
        height: 100%;
        border: none;
    }
    
    .olleksi-btn-hidden {
        display: none !important;
    }
    
    @media (max-width: 768px) {
        .gallery-modal {
            align-items: flex-end;
        }
        .gallery-modal-content {
            width: 100%;
            height: 85%;
            max-height: 85%;
            border-radius: 15px 15px 0 0;
        }
    }';
}

// ─── JavaScript для форуму ─────────────────────────────────────────────────
function getForumScript(): string
{
    return <<<'JS'
(function() {
    'use strict';
    
    var config = window.OlleksiBBCodesConfig || {};
    var galleryModal = null;
    var observer = null;
    
    function debounce(func, wait) {
        var timeout;
        return function() {
            var context = this;
            var args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                func.apply(context, args);
            }, wait);
        };
    }
    
    function insertAtCursor(textarea, open, close) {
    if (!textarea || textarea.tagName !== 'TEXTAREA') return false;
    
    try {
        // Зберігаємо поточну позицію прокрутки
        var scrollTop = textarea.scrollTop;
        
        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var sel = textarea.value.substring(start, end);
        
        var replacement = open + sel + close;
        
        textarea.value = textarea.value.substring(0, start) + 
                       replacement + 
                       textarea.value.substring(end);
        
        // Відновлюємо позицію прокрутки перед focus
        textarea.scrollTop = scrollTop;
        
        textarea.focus({ preventScroll: true });
        
        var newCursorPos = start + open.length + sel.length;
        textarea.setSelectionRange(newCursorPos, newCursorPos);
        
        // Знову відновлюємо позицію прокрутки після setSelectionRange
        textarea.scrollTop = scrollTop;
        
        setTimeout(function() {
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
            // Фінальне відновлення прокрутки
            textarea.scrollTop = scrollTop;
        }, 0);
        
        return true;
    } catch (e) {
        console.warn('Olleksi BBCodes: Insert failed', e);
        return false;
    }
}
    
    function hideStandardButtons(toolbar) {
        var hideMap = config.hideMap || {};
        
        var hasHides = false;
        for (var key in hideMap) {
            if (hideMap[key]) {
                hasHides = true;
                break;
            }
        }
        if (!hasHides) return;
        
        var buttons = toolbar.querySelectorAll('button, .Button');
        
        for (var i = 0; i < buttons.length; i++) {
            var button = buttons[i];
            var icons = button.querySelectorAll('.icon, .fas, .far, .fal');
            
            for (var j = 0; j < icons.length; j++) {
                var icon = icons[j];
                for (var iconClass in hideMap) {
                    if (hideMap.hasOwnProperty(iconClass) && 
                        hideMap[iconClass] && 
                        icon.classList.contains(iconClass)) {
                        
                        button.style.display = 'none';
                        button.setAttribute('data-olleksi-hidden', 'true');
                        break;
                    }
                }
            }
        }
    }
    
    function addCustomButtons(toolbar) {
        var bbcodes = config.customBbcodes || [];
        if (bbcodes.length === 0) return;
        
        for (var i = 0; i < bbcodes.length; i++) {
            var bb = bbcodes[i];
            if (!bb.visible) continue;
            
            var btnId = 'olleksi-custom-btn-' + bb.name.replace(/[^a-zA-Z0-9]/g, '-');
            if (toolbar.querySelector('#' + btnId)) continue;
            
            var btn = document.createElement('button');
            btn.id = btnId;
            btn.type = 'button';
            btn.className = 'Button Button--icon hasIcon';
            btn.title = bb.tooltip || bb.name;
            btn.setAttribute('aria-label', bb.tooltip || bb.name);
            btn._olleksiData = { open: bb.open, close: bb.close, toolbar: toolbar };
            
            var icon = document.createElement('i');
            icon.className = 'icon fas ' + bb.icon;
            icon.setAttribute('aria-hidden', 'true');
            btn.appendChild(icon);
            
            toolbar.appendChild(btn);
        }
    }
    
    function handleCustomButtonClick(e) {
        var btn = e.target.closest('button[id^="olleksi-custom-btn-"]');
        if (!btn || !btn._olleksiData) return;
        
        e.preventDefault();
        e.stopPropagation();
        
        var data = btn._olleksiData;
        var textarea = data.toolbar.closest('.TextEditor')?.querySelector('textarea');
        if (textarea) {
            insertAtCursor(textarea, data.open, data.close);
        }
    }
    
    function addGalleryButton(toolbar) {
        if (toolbar.querySelector('#olleksi-gallery-btn')) return;
        
        var btn = document.createElement('button');
        btn.id = 'olleksi-gallery-btn';
        btn.type = 'button';
        btn.className = 'Button Button--icon hasIcon';
        btn.title = 'Галерея Таро';
        btn.setAttribute('aria-label', 'Галерея Таро');
        
        var icon = document.createElement('i');
        icon.className = 'icon fas fa-images';
        icon.setAttribute('aria-hidden', 'true');
        btn.appendChild(icon);
        
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            showGalleryModal();
        });
        
        if (toolbar.children.length >= 1) {
            toolbar.insertBefore(btn, toolbar.children[1]);
        } else {
            toolbar.appendChild(btn);
        }
    }
    
    function processToolbar(toolbar) {
        if (!toolbar || toolbar.hasAttribute('data-olleksi-processed')) return;
        
        hideStandardButtons(toolbar);
        addCustomButtons(toolbar);
        addGalleryButton(toolbar);
        
        toolbar.setAttribute('data-olleksi-processed', 'true');
    }
    
    function createGalleryModal() {
        if (galleryModal) return galleryModal;
        
        galleryModal = document.createElement('div');
        galleryModal.id = 'olleksi-gallery-modal';
        galleryModal.className = 'gallery-modal';
        galleryModal.setAttribute('role', 'dialog');
        
        galleryModal.innerHTML = '<div class="gallery-modal-content">' +
    '<div class="gallery-modal-body">' +
    '<iframe src="' + (config.galleryUrl || '/gallery') + '" ' +
    'sandbox="allow-scripts allow-same-origin" ' +
    'loading="lazy" ' +
    'title="Галерея Таро"></iframe>' +
    '</div>' +
    '<div class="gallery-modal-footer">' +
    '<button class="gallery-close-btn" id="olleksi-gallery-close" aria-label="Згорнути">' +
    'Згорнути галерею' +
    '</button>' +
    '</div></div>';
        
        document.body.appendChild(galleryModal);
        
        var closeBtn = galleryModal.querySelector('#olleksi-gallery-close');
        closeBtn.addEventListener('click', hideGalleryModal);
        
        galleryModal.addEventListener('click', function(e) {
            if (e.target === galleryModal) {
                hideGalleryModal();
            }
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && galleryModal.style.display === 'flex') {
                hideGalleryModal();
            }
        });
        
        return galleryModal;
    }
    
    function showGalleryModal() {
        var modal = createGalleryModal();
        modal.style.display = 'flex';
    }
    
    function hideGalleryModal() {
        if (galleryModal) {
            galleryModal.style.display = 'none';
        }
    }
    
    window.insertToEditor = function(code) {
        if (!code || typeof code !== 'string' || code.length > 500) return;
        
        var textarea = document.querySelector('textarea.FormControl.Composer-flexible.TextEditor-editor');
        if (textarea) {
            insertAtCursor(textarea, code, '');
        }
    };
    
    window.addEventListener('message', function(event) {
        if (!event.data || typeof event.data !== 'object') return;
        
        if (event.data.type === 'insertCard' && event.data.bbcode) {
            var bbcode = String(event.data.bbcode).trim();
            if (bbcode.length > 0 && bbcode.length < 500) {
                window.insertToEditor(bbcode);
                hideGalleryModal();
            }
        }
    });
    
    function setupMutationObserver() {
        if (observer) return;
        
        observer = new MutationObserver(function(mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var mutation = mutations[i];
                if (mutation.addedNodes.length) {
                    for (var j = 0; j < mutation.addedNodes.length; j++) {
                        var node = mutation.addedNodes[j];
                        if (node.nodeType === 1) {
                            if (node.classList && node.classList.contains('TextEditor-controls')) {
                                processToolbar(node);
                            }
                            if (node.querySelectorAll) {
                                var toolbars = node.querySelectorAll('.TextEditor-controls:not([data-olleksi-processed])');
                                for (var k = 0; k < toolbars.length; k++) {
                                    processToolbar(toolbars[k]);
                                }
                            }
                        }
                    }
                }
            }
        });
        
        var target = document.getElementById('composer') || document.body;
        observer.observe(target, {
            childList: true,
            subtree: true
        });
    }
    
    function setupEventListeners() {
        document.addEventListener('click', handleCustomButtonClick, true);
        
        var debouncedProcess = debounce(function() {
            var toolbars = document.querySelectorAll('.TextEditor-controls:not([data-olleksi-processed])');
            for (var i = 0; i < toolbars.length; i++) {
                processToolbar(toolbars[i]);
            }
        }, 150);
        
        document.addEventListener('click', function(e) {
            var target = e.target.closest('.Button--primary, .Post-edit, .Post-comment, .Button--link, .Post-quoteButton');
            if (target) {
                setTimeout(debouncedProcess, 100);
            }
        });
        
        document.addEventListener('flarum:loaded', debouncedProcess);
        document.addEventListener('flarum:modal-opened', debouncedProcess);
    }
    
    function init() {
        var existingToolbars = document.querySelectorAll('.TextEditor-controls:not([data-olleksi-processed])');
        for (var i = 0; i < existingToolbars.length; i++) {
            processToolbar(existingToolbars[i]);
        }
        
        setupMutationObserver();
        setupEventListeners();
        
        if (config.debug) {
            console.log('Olleksi BBCodes: Initialized');
        }
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
JS;
}

// ─── JavaScript для адмін панелі ────────────────────────────────────────────
function getAdminScript(string $initialBbcodes): string
{
    return <<<JS
(function() {
    'use strict';
    
    var initAttempts = 0;
    var maxAttempts = 10;
    var bbcodes = [];
    var isSaving = false;
    
    function tryInit() {
        if (initAttempts >= maxAttempts) return;
        initAttempts++;
        
        if (!window.app || !window.app.extensionData) {
            setTimeout(tryInit, 500);
            return;
        }
        
        try {
            initializeExtension();
        } catch(e) {
            console.error('BBCodes admin init error:', e);
            setTimeout(tryInit, 1000);
        }
    }
    
    function initializeExtension() {
        app.extensionData.for('forumtaro-bbcodes')
            .registerSetting({ setting: 'forumtaro-bbcodes.hide_bold',      type: 'boolean', label: 'Приховати жирний (bold)' })
            .registerSetting({ setting: 'forumtaro-bbcodes.hide_italic',    type: 'boolean', label: 'Приховати курсив (italic)' })
            .registerSetting({ setting: 'forumtaro-bbcodes.hide_underline', type: 'boolean', label: 'Приховати підкреслення (underline)' })
            .registerSetting({ setting: 'forumtaro-bbcodes.hide_link',      type: 'boolean', label: 'Приховати посилання (link)' })
            .registerSetting({ setting: 'forumtaro-bbcodes.hide_image',     type: 'boolean', label: 'Приховати зображення (image)' })
            .registerSetting({ setting: 'forumtaro-bbcodes.hide_code',      type: 'boolean', label: 'Приховати код (code)' })
            .registerSetting({ setting: 'forumtaro-bbcodes.hide_quote',     type: 'boolean', label: 'Приховати цитату (quote)' })
            .registerSetting({ setting: 'forumtaro-bbcodes.hide_strike',    type: 'boolean', label: 'Приховати закреслення (strike)' })
            .registerSetting({ setting: 'forumtaro-bbcodes.hide_header',    type: 'boolean', label: 'Приховати заголовок (header)' })
            .registerSetting({ setting: 'forumtaro-bbcodes.hide_list',      type: 'boolean', label: 'Приховати списки (list)' })
            .registerSetting({ setting: 'forumtaro-bbcodes.hide_spoiler',   type: 'boolean', label: 'Приховати спойлер (spoiler)' })
            .registerSetting({ setting: 'forumtaro-bbcodes.hide_mention',   type: 'boolean', label: 'Приховати згадування (mention)' })
            .registerSetting({ setting: 'forumtaro-bbcodes.hide_preview',   type: 'boolean', label: 'Приховати попередній перегляд (preview)' });

        var initialData = {$initialBbcodes};
        bbcodes = Array.isArray(initialData) ? JSON.parse(JSON.stringify(initialData)) : [];
        
        startUIInitialization();
    }
    
    function save(successMsg) {
        if (isSaving) return;
        isSaving = true;
        
        var saveBtn = document.getElementById('olleksi-save-btn');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Збереження...';
        }
        
        app.request({
            method: 'POST',
            url: app.forum.attribute('apiUrl') + '/forumtaro-bbcodes',
            body: { bbcodes: bbcodes }
        }).then(function(data) {
            if (data.success && data.bbcodes) {
                bbcodes = data.bbcodes;
                render();
                showMsg(successMsg || 'Збережено!', false);
            } else {
                showMsg('Помилка: неочікувана відповідь сервера', true);
            }
        }).catch(function(err) {
            console.error('BBCodes save error:', err);
            showMsg('Помилка збереження: ' + (err.message || 'Невідома помилка'), true);
        }).finally(function() {
            isSaving = false;
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Зберегти';
            }
        });
    }
    
    function showMsg(text, isErr) {
        var el = document.getElementById('olleksi-msg');
        if (!el) return;
        el.textContent = text;
        el.style.color = isErr ? '#c0392b' : '#27ae60';
        el.style.display = 'block';
        
        setTimeout(function() {
            el.style.display = 'none';
        }, 3000);
    }
    
    function escHtml(s) {
        if (!s) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
    
    function render() {
        var wrap = document.getElementById('olleksi-bbcode-list');
        if (!wrap) return;
        
        if (bbcodes.length === 0) {
            wrap.innerHTML = '<p style="color:#999;font-size:13px;padding:12px">Немає кастомних BB-кодів. Додайте новий код нижче.</p>';
            return;
        }
        
        var rows = bbcodes.map(function(bb, i) {
            var visibilityBtn = bb.visible ? 
                '<button class="Button Button--primary" style="font-size:11px;padding:2px 8px" data-action="toggle" data-index="' + i + '">👁 Видно</button>' :
                '<button class="Button" style="font-size:11px;padding:2px 8px" data-action="toggle" data-index="' + i + '">🚫 Прих.</button>';
            
            return '<div style="display:flex;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid #eee;">' +
                '<i class="fas ' + escHtml(bb.icon) + '" style="width:20px;text-align:center;color:#555"></i>' +
                '<span style="flex:1;font-size:13px;font-weight:500">' + escHtml(bb.name) + '</span>' +
                '<span style="font-size:11px;color:#888;flex:2">' + escHtml(bb.tooltip) + '</span>' +
                '<span style="font-size:10px;color:#aaa;font-family:monospace;flex:2">' + escHtml(bb.open) + ' ... ' + escHtml(bb.close) + '</span>' +
                '<button class="Button Button--primary" style="font-size:11px;padding:2px 8px" data-action="edit" data-index="' + i + '">Ред.</button>' +
                visibilityBtn +
                '<button class="Button Button--danger" style="font-size:11px;padding:2px 8px" data-action="delete" data-index="' + i + '">X</button>' +
            '</div>';
        }).join('');
        
        wrap.innerHTML = rows;
    }
    
    function resetForm() {
        document.getElementById('olleksi-f-idx').value = '-1';
        document.getElementById('olleksi-f-name').value = '';
        document.getElementById('olleksi-f-icon').value = '';
        document.getElementById('olleksi-f-tooltip').value = '';
        document.getElementById('olleksi-f-open').value = '';
        document.getElementById('olleksi-f-close').value = '';
        document.getElementById('olleksi-form-title').textContent = 'Новий BB-код';
    }
    
    function createUI(container) {
        if (document.getElementById('olleksi-bbcode-content')) return;
        
        var ui = document.createElement('div');
        ui.id = 'olleksi-bbcode-content';
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
            '<input id="olleksi-f-name" class="FormControl" placeholder="напр. Спойлер" style="width:100%" maxlength="32"></div>' +
            '<div><label style="font-size:12px;color:#555">FA іконка</label>' +
            '<input id="olleksi-f-icon" class="FormControl" placeholder="fa-star" style="width:100%" maxlength="32"></div>' +
            '</div>' +
            '<div style="margin-bottom:8px"><label style="font-size:12px;color:#555">Підказка (tooltip)</label>' +
            '<input id="olleksi-f-tooltip" class="FormControl" placeholder="Текст при наведенні" style="width:100%" maxlength="64"></div>' +
            '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px">' +
            '<div><label style="font-size:12px;color:#555">Відкриваючий тег</label>' +
            '<input id="olleksi-f-open" class="FormControl" placeholder="[spoiler]" style="width:100%" maxlength="128"></div>' +
            '<div><label style="font-size:12px;color:#555">Закриваючий тег</label>' +
            '<input id="olleksi-f-close" class="FormControl" placeholder="[/spoiler]" style="width:100%" maxlength="128"></div>' +
            '</div>' +
            '<div style="display:flex;gap:8px">' +
            '<button class="Button Button--primary" id="olleksi-save-btn">Зберегти</button>' +
            '<button class="Button" id="olleksi-reset-btn">Скинути форму</button>' +
            '</div>' +
            '</div></div>';
        
        container.appendChild(ui);
        
        var list = document.getElementById('olleksi-bbcode-list');
        list.addEventListener('click', function(e) {
            var target = e.target.closest('[data-action]');
            if (!target) return;
            
            var action = target.dataset.action;
            var index = parseInt(target.dataset.index);
            
            if (isNaN(index) || index < 0 || index >= bbcodes.length) return;
            
            if (action === 'edit') {
                var bb = bbcodes[index];
                document.getElementById('olleksi-f-name').value = bb.name;
                document.getElementById('olleksi-f-icon').value = bb.icon;
                document.getElementById('olleksi-f-tooltip').value = bb.tooltip;
                document.getElementById('olleksi-f-open').value = bb.open;
                document.getElementById('olleksi-f-close').value = bb.close;
                document.getElementById('olleksi-f-idx').value = index;
                document.getElementById('olleksi-form-title').textContent = 'Редагувати BB-код';
            } else if (action === 'toggle') {
                bbcodes[index].visible = !bbcodes[index].visible;
                save('Видимість змінено');
            } else if (action === 'delete') {
                if (confirm('Видалити "' + bbcodes[index].name + '"?')) {
                    bbcodes.splice(index, 1);
                    save('Видалено');
                    resetForm();
                }
            }
        });
        
        document.getElementById('olleksi-save-btn').addEventListener('click', function() {
            var idx = parseInt(document.getElementById('olleksi-f-idx').value);
            var name = document.getElementById('olleksi-f-name').value.trim();
            var icon = document.getElementById('olleksi-f-icon').value.trim() || 'fa-star';
            var tooltip = document.getElementById('olleksi-f-tooltip').value.trim();
            var open = document.getElementById('olleksi-f-open').value;
            var close = document.getElementById('olleksi-f-close').value;
            
            if (!name) { 
                showMsg('Вкажіть назву кнопки', true); 
                return; 
            }
            
            var entry = { 
                name: name, 
                icon: icon, 
                tooltip: tooltip, 
                open: open, 
                close: close, 
                visible: true 
            };
            
            if (idx >= 0 && idx < bbcodes.length) {
                entry.visible = bbcodes[idx].visible;
                bbcodes[idx] = entry;
            } else {
                bbcodes.push(entry);
            }
            
            save('Збережено!');
            resetForm();
        });
        
        document.getElementById('olleksi-reset-btn').addEventListener('click', resetForm);
        
        render();
    }
    
    function isBBcodesExtensionPage() {
        // Перевіряємо URL сторінки
        var currentUrl = window.location.href;
        if (currentUrl.indexOf('/admin/extension/forumtaro-bbcodes') !== -1) {
            return true;
        }
        
        // Перевіряємо наявність специфічних елементів розширення BBCodes
        var extensionId = 'forumtaro-bbcodes';
        
        // Перевіряємо через data-атрибути або класи, специфічні для нашої сторінки
        var extensionHeader = document.querySelector('.ExtensionPage h2, .ExtensionPage h3');
        if (extensionHeader) {
            var headerText = extensionHeader.textContent || extensionHeader.innerText;
            if (headerText.toLowerCase().indexOf('bbcodes') !== -1 || 
                headerText.toLowerCase().indexOf('bb-codes') !== -1) {
                return true;
            }
        }
        
        // Перевіряємо через активне меню або навігацію
        var activeMenuItem = document.querySelector('.ExtensionNav-item.active, .ExtensionNav-link.active');
        if (activeMenuItem) {
            var menuText = activeMenuItem.textContent || activeMenuItem.innerText;
            if (menuText.toLowerCase().indexOf('bbcodes') !== -1 || 
                menuText.toLowerCase().indexOf('bb-codes') !== -1) {
                return true;
            }
        }
        
        // Перевіряємо через container сторінки розширення
        var extensionContainer = document.querySelector('.ExtensionPage');
        if (extensionContainer) {
            var pageTitle = extensionContainer.querySelector('.ExtensionPage-header h2, .ExtensionPage-header h3');
            if (pageTitle) {
                var titleText = pageTitle.textContent || pageTitle.innerText;
                if (titleText.toLowerCase().indexOf('bbcodes') !== -1 || 
                    titleText.toLowerCase().indexOf('bb-codes') !== -1) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    function findAndCreateUI() {
        if (!isBBcodesExtensionPage()) {
            return false;
        }
        
        // Шукаємо контейнер для вставки нашого UI
        var forms = document.querySelectorAll('.Form, .ExtensionPage-settings');
        if (forms.length === 0) {
            // Спробуємо знайти інший підходящий контейнер
            var extensionContainer = document.querySelector('.ExtensionPage');
            if (extensionContainer) {
                createUI(extensionContainer);
                return true;
            }
            return false;
        }
        
        var container = forms[forms.length - 1];
        createUI(container);
        return true;
    }
    
    function startUIInitialization() {
        // Спочатку перевіряємо, чи ми на потрібній сторінці
        if (!isBBcodesExtensionPage()) {
            // Якщо ні, то припиняємо виконання
            return;
        }
        
        // Якщо так, то пробуємо створити UI з кількома спробами
        setTimeout(function() {
            if (!findAndCreateUI()) {
                setTimeout(findAndCreateUI, 300);
                setTimeout(findAndCreateUI, 600);
                setTimeout(findAndCreateUI, 1000);
            }
        }, 300);
    }
    
    setTimeout(tryInit, 500);
})();
JS;
}

// ─── Основний екстеншн ──────────────────────────────────────────────────────
return [
    (new Extend\Routes('api'))
        ->post('/forumtaro-bbcodes', 'forumtaro.bbcodes.save', SaveBbcodesHandler::class),

    (new Extend\Frontend('forum'))
        ->content(function (\Flarum\Frontend\Document $document) {
            $settings = resolve(SettingsRepositoryInterface::class);
            $validated = getValidatedSettings($settings);
            
            $config = [
                'hideMap' => [
                    'fa-bold' => $validated['hide_bold'],
                    'fa-italic' => $validated['hide_italic'],
                    'fa-underline' => $validated['hide_underline'],
                    'fa-link' => $validated['hide_link'],
                    'fa-image' => $validated['hide_image'],
                    'fa-code' => $validated['hide_code'],
                    'fa-quote-left' => $validated['hide_quote'],
                    'fa-strikethrough' => $validated['hide_strike'],
                    'fa-heading' => $validated['hide_header'],
                    'fa-list-ul' => $validated['hide_list'],
                    'fa-list-ol' => $validated['hide_list'],
                    'fa-exclamation-triangle' => $validated['hide_spoiler'],
                    'fa-at' => $validated['hide_mention'],
                    'fa-eye' => $validated['hide_preview']
                ],
                'customBbcodes' => $validated['custom_bbcodes'],
                'galleryUrl' => '/gallery',
                'debug' => false
            ];
            
            $configJson = json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
            
            $dynamicCss = generateDynamicCss($validated);
            $document->head[] = "<style>" . getStyles() . "\n" . $dynamicCss . "</style>";

            $document->foot[] = "<script>
window.OlleksiBBCodesConfig = {$configJson};
" . getForumScript() . "
</script>";
        }),

    (new Extend\Frontend('admin'))
        ->content(function (\Flarum\Frontend\Document $document) {
            $settings = resolve(SettingsRepositoryInterface::class);
            $validated = getValidatedSettings($settings);
            
            $initialBbcodes = json_encode($validated['custom_bbcodes'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
            
            $document->foot[] = "<script>" . getAdminScript($initialBbcodes) . "</script>";
        }),

    (new Extend\Settings())
        ->default('forumtaro-bbcodes.hide_bold', false)
        ->default('forumtaro-bbcodes.hide_italic', false)
        ->default('forumtaro-bbcodes.hide_underline', false)
        ->default('forumtaro-bbcodes.hide_link', false)
        ->default('forumtaro-bbcodes.hide_image', false)
        ->default('forumtaro-bbcodes.hide_code', false)
        ->default('forumtaro-bbcodes.hide_quote', false)
        ->default('forumtaro-bbcodes.hide_strike', false)
        ->default('forumtaro-bbcodes.hide_header', false)
        ->default('forumtaro-bbcodes.hide_list', false)
        ->default('forumtaro-bbcodes.hide_spoiler', false)
        ->default('forumtaro-bbcodes.hide_mention', false)
        ->default('forumtaro-bbcodes.hide_preview', false)
        ->default('forumtaro-bbcodes.custom_bbcodes', '[]')
        ->serializeToForum('forumtaro-bbcodes.hide_bold',      'forumtaro-bbcodes.hide_bold',      'boolval')
        ->serializeToForum('forumtaro-bbcodes.hide_italic',    'forumtaro-bbcodes.hide_italic',    'boolval')
        ->serializeToForum('forumtaro-bbcodes.hide_underline', 'forumtaro-bbcodes.hide_underline', 'boolval')
        ->serializeToForum('forumtaro-bbcodes.hide_link',      'forumtaro-bbcodes.hide_link',      'boolval')
        ->serializeToForum('forumtaro-bbcodes.hide_image',     'forumtaro-bbcodes.hide_image',     'boolval')
        ->serializeToForum('forumtaro-bbcodes.hide_code',      'forumtaro-bbcodes.hide_code',      'boolval')
        ->serializeToForum('forumtaro-bbcodes.hide_quote',     'forumtaro-bbcodes.hide_quote',     'boolval')
        ->serializeToForum('forumtaro-bbcodes.hide_strike',    'forumtaro-bbcodes.hide_strike',    'boolval')
        ->serializeToForum('forumtaro-bbcodes.hide_header',    'forumtaro-bbcodes.hide_header',    'boolval')
        ->serializeToForum('forumtaro-bbcodes.hide_list',      'forumtaro-bbcodes.hide_list',      'boolval')
        ->serializeToForum('forumtaro-bbcodes.hide_spoiler',   'forumtaro-bbcodes.hide_spoiler',   'boolval')
        ->serializeToForum('forumtaro-bbcodes.hide_mention',   'forumtaro-bbcodes.hide_mention',   'boolval')
        ->serializeToForum('forumtaro-bbcodes.hide_preview',   'forumtaro-bbcodes.hide_preview',   'boolval')
        ->serializeToForum('forumtaro-bbcodes.custom_bbcodes', 'forumtaro-bbcodes.custom_bbcodes'),
];
