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
        $actor->assertAdmin();

        $body = $request->getParsedBody();
        $bbcodes = Arr::get($body, 'bbcodes', []);

        $clean = array_values(array_filter(array_map(function ($item) {
            if (empty($item['name'])) return null;
            return [
                'name'    => substr(trim($item['name']), 0, 32),
                'icon'    => substr(trim($item['icon'] ?? 'fa-star'), 0, 32),
                'tooltip' => substr(trim($item['tooltip'] ?? ''), 0, 64),
                'open'    => substr(trim($item['open'] ?? ''), 0, 128),
                'close'   => substr(trim($item['close'] ?? ''), 0, 128),
                'visible' => (bool)($item['visible'] ?? true),
            ];
        }, $bbcodes)));

        $this->settings->set('forumtaro-bbcodes.custom_bbcodes', json_encode($clean));

        return new JsonResponse(['success' => true, 'bbcodes' => $clean]);
    }
}

return [
    // ─── API роут для збереження BB-кодів ────────────────────────────────────
    (new Extend\Routes('api'))
        ->post('/forumtaro-bbcodes', 'forumtaro.bbcodes.save', SaveBbcodesHandler::class),

    // ─── Форум ───────────────────────────────────────────────────────────────
    (new Extend\Frontend('forum'))
        ->content(function (\Flarum\Frontend\Document $document) {
            $settings = resolve(SettingsRepositoryInterface::class);

            $hideBold        = $settings->get('forumtaro-bbcodes.hide_bold', false);
            $hideItalic      = $settings->get('forumtaro-bbcodes.hide_italic', false);
            $hideUnderline   = $settings->get('forumtaro-bbcodes.hide_underline', false);
            $hideLink        = $settings->get('forumtaro-bbcodes.hide_link', false);
            $hideImage       = $settings->get('forumtaro-bbcodes.hide_image', false);
            $hideCode        = $settings->get('forumtaro-bbcodes.hide_code', false);
            $hideQuote       = $settings->get('forumtaro-bbcodes.hide_quote', false);
            $hideStrike      = $settings->get('forumtaro-bbcodes.hide_strike', false);
            $hideHeader      = $settings->get('forumtaro-bbcodes.hide_header', false);
            $hideList        = $settings->get('forumtaro-bbcodes.hide_list', false);
            $hideSpoiler     = $settings->get('forumtaro-bbcodes.hide_spoiler', false);
            $hideMention     = $settings->get('forumtaro-bbcodes.hide_mention', false);
            $hidePreview     = $settings->get('forumtaro-bbcodes.hide_preview', false);

            $customBbcodesJson = $settings->get('forumtaro-bbcodes.custom_bbcodes', '[]');
            $customBbcodes = json_encode(json_decode($customBbcodesJson, true) ?: []);

            $hideMapJson = json_encode([
                'fa-bold' => $hideBold,
                'fa-italic' => $hideItalic,
                'fa-underline' => $hideUnderline,
                'fa-link' => $hideLink,
                'fa-image' => $hideImage,
                'fa-code' => $hideCode,
                'fa-quote-left' => $hideQuote,
                'fa-strikethrough' => $hideStrike,
                'fa-heading' => $hideHeader,
                'fa-list-ul' => $hideList,
                'fa-list-ol' => $hideList,
                'fa-exclamation-triangle' => $hideSpoiler,
                'fa-at' => $hideMention,
                'fa-eye' => $hidePreview
            ]);

            // CSS та JS для галереї + основний функціонал
            $document->head[] = '<style>
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
    border-radius: 10px;
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
}
.gallery-close:hover {
    background: #f0f0f0;
}
.gallery-modal-body {
    flex: 1;
    overflow: auto;
    padding: 0;
}
.gallery-modal-body iframe {
    width: 100%;
    height: 100%;
    border: none;
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
}
</style>
<script>
window.olleksiHomepageInit = function() {
    var hideMap = ' . $hideMapJson . ';
    var customBbcodes = ' . $customBbcodes . ';

    function insertAtCursor(textarea, open, close) {
        if (!textarea) return;
        var start = textarea.selectionStart;
        var end   = textarea.selectionEnd;
        var sel   = textarea.value.substring(start, end);
        var replacement = open + sel + close;
        textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
        textarea.focus();
        textarea.selectionStart = start + open.length;
        textarea.selectionEnd = start + open.length + sel.length;
        textarea.dispatchEvent(new Event("input", { bubbles: true }));
    }

    function hideStandardButtons(toolbar) {
        if (!toolbar) return;
        
        var buttons = toolbar.querySelectorAll("button, .Button");
        buttons.forEach(function(button) {
            var icon = button.querySelector(".icon, .fas, .far, .fal");
            if (!icon) return;
            
            for (var iconClass in hideMap) {
                if (hideMap[iconClass] && icon.classList.contains(iconClass)) {
                    button.style.setProperty("display", "none", "important");
                    button.setAttribute("data-olleksi-hidden", "true");
                    return;
                }
            }
        });
    }

    function addCustomButtons(toolbar) {
        if (!toolbar) return;
        
        customBbcodes.forEach(function(bb) {
            if (!bb.visible) return;
            
            var btnId = "olleksi-custom-btn-" + bb.name.replace(/[^a-zA-Z0-9]/g, "-");
            if (toolbar.querySelector("#" + btnId)) return;

            var btn = document.createElement("button");
            btn.id = btnId;
            btn.className = "Button Button--icon hasIcon";
            btn.title = bb.tooltip || bb.name;
            btn.type = "button";
            btn.setAttribute("aria-label", bb.tooltip || bb.name);
            btn.innerHTML = \'<i class="icon fas \' + bb.icon + \'" aria-hidden="true"></i>\';
            
            btn.addEventListener("click", function(e) {
                e.preventDefault();
                e.stopPropagation();
                var textarea = toolbar.closest(".TextEditor")?.querySelector("textarea");
                if (textarea) {
                    insertAtCursor(textarea, bb.open, bb.close);
                }
            });
            
            toolbar.appendChild(btn);
        });
    }

    function processToolbar(toolbar) {
        if (!toolbar || toolbar.hasAttribute("data-olleksi-done")) return;
        toolbar.setAttribute("data-olleksi-done", "true");
        
        hideStandardButtons(toolbar);
        addCustomButtons(toolbar);
        addGalleryButtonToToolbar(toolbar);
    }

    function processAllToolbars() {
        var toolbars = document.querySelectorAll(".TextEditor-controls:not([data-olleksi-done])");
        toolbars.forEach(processToolbar);
        
        document.querySelectorAll(".TextEditor-controls[data-olleksi-done]").forEach(function(toolbar) {
            var newButtons = toolbar.querySelectorAll("button:not([data-olleksi-hidden]):not([id^=olleksi-custom-btn-]):not(#galleryBtn)");
            if (newButtons.length > 0) {
                hideStandardButtons(toolbar);
            }
        });
    }

    // ─── Галерея Таро ─────────────────────────────────────────
    var galleryModal = null;

    window.insertToEditor = function(code) {
        var textarea = document.querySelector("textarea.FormControl.Composer-flexible.TextEditor-editor");
        if (textarea) {
            var start = textarea.selectionStart;
            var end = textarea.selectionEnd;
            var value = textarea.value;
            textarea.value = value.substring(0, start) + code + value.substring(end);
            textarea.setSelectionRange(start + code.length, start + code.length);
            textarea.focus();
            textarea.dispatchEvent(new Event("input", { bubbles: true }));
        }
    };

    window.showGalleryModal = function() {
        if (!galleryModal) {
            galleryModal = document.createElement("div");
            galleryModal.id = "galleryModal";
            galleryModal.className = "gallery-modal";
            galleryModal.innerHTML = \'<div class="gallery-modal-content">\' +
                \'<div class="gallery-modal-header">\' +
                \'<h3>📷 Галерея Таро</h3>\' +
                \'<button class="gallery-close" id="galleryModalClose">×</button>\' +
                \'</div>\' +
                \'<div class="gallery-modal-body">\' +
                \'<iframe src="https://test.tarot.pp.ua/0/gallery.html" style="width:100%;height:100%;border:none;"></iframe>\' +
                \'</div>\' +
                \'</div>\';
            document.body.appendChild(galleryModal);
            
            document.getElementById("galleryModalClose").onclick = function() {
                galleryModal.style.display = "none";
            };
            galleryModal.onclick = function(e) {
                if (e.target === galleryModal) galleryModal.style.display = "none";
            };
        }
        galleryModal.style.display = "flex";
    };

    window.addEventListener("message", function(event) {
        if (event.data && event.data.type === "insertCard") {
            window.insertToEditor(event.data.bbcode);
            if (galleryModal) galleryModal.style.display = "none";
        }
    });

    function addGalleryButtonToToolbar(toolbar) {
        if (!toolbar || toolbar.querySelector("#galleryBtn")) return;
        
        var btn = document.createElement("button");
        btn.id = "galleryBtn";
        btn.className = "Button Button--icon hasIcon";
        btn.title = "Галерея Таро";
        btn.type = "button";
        btn.setAttribute("aria-label", "Галерея Таро");
        btn.innerHTML = \'<i class="icon fas fa-images" aria-hidden="true"></i>\';
        btn.onclick = function(e) {
            e.preventDefault();
            window.showGalleryModal();
        };
        toolbar.appendChild(btn);
    }
    // ─── Кінець галереї ─────────────────────────────────────

    setTimeout(processAllToolbars, 100);

    var observer = new MutationObserver(function(mutations) {
        var shouldCheck = false;
        
        mutations.forEach(function(mutation) {
            mutation.addedNodes.forEach(function(node) {
                if (node.nodeType === 1) {
                    if (node.classList && node.classList.contains("TextEditor-controls")) {
                        shouldCheck = true;
                    }
                    if (node.querySelectorAll) {
                        if (node.querySelectorAll(".TextEditor-controls").length > 0) {
                            shouldCheck = true;
                        }
                        if (node.querySelectorAll(".Button, button").length > 0) {
                            shouldCheck = true;
                        }
                    }
                }
            });
            
            if (mutation.target.classList && mutation.target.classList.contains("TextEditor-controls")) {
                shouldCheck = true;
            }
        });
        
        if (shouldCheck) {
            setTimeout(processAllToolbars, 50);
        }
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ["class", "style"]
    });

    setInterval(function() {
        var unprocessed = document.querySelectorAll(".TextEditor-controls:not([data-olleksi-done])");
        if (unprocessed.length > 0) {
            processAllToolbars();
        }
        
        document.querySelectorAll(".TextEditor-controls[data-olleksi-done]").forEach(function(tb) {
            if (tb.offsetParent === null) {
                tb.removeAttribute("data-olleksi-done");
            }
        });
    }, 200);

    document.addEventListener("click", function(e) {
        var target = e.target;
        if (target.closest(".Button--primary, .Post-edit, .Post-comment, .Button--link, .Post-quoteButton")) {
            setTimeout(processAllToolbars, 100);
            setTimeout(processAllToolbars, 300);
            setTimeout(processAllToolbars, 500);
        }
    }, true);

    document.addEventListener("flarum:loaded", function() {
        setTimeout(processAllToolbars, 100);
        setTimeout(processAllToolbars, 300);
        setTimeout(processAllToolbars, 500);
    });
    
    document.addEventListener("flarum:modal-opened", function() {
        setTimeout(processAllToolbars, 200);
    });
};

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function() {
        setTimeout(window.olleksiHomepageInit, 200);
    });
} else {
    setTimeout(window.olleksiHomepageInit, 200);
}

window.addEventListener("load", function() {
    setTimeout(window.olleksiHomepageInit, 500);
});
</script>';
        }),

    // ─── Адмін панель ────────────────────────────────────────────────────────
    (new Extend\Frontend('admin'))
        ->content(function (\Flarum\Frontend\Document $document) {
            $settings = resolve(SettingsRepositoryInterface::class);
            $customBbcodesJson = $settings->get('forumtaro-bbcodes.custom_bbcodes', '[]');
            $customBbcodes = json_encode(json_decode($customBbcodesJson, true) ?: []);

            $document->head[] = '<script>
window.addEventListener("load", function() {
    setTimeout(function() {
        if (!window.app || !window.app.extensionData) return;

        try {
            app.extensionData.for("forumtaro-bbcodes")
                .registerSetting({ setting: "forumtaro-bbcodes.hide_bold",      type: "boolean", label: "Приховати жирний (bold)" })
                .registerSetting({ setting: "forumtaro-bbcodes.hide_italic",    type: "boolean", label: "Приховати курсив (italic)" })
                .registerSetting({ setting: "forumtaro-bbcodes.hide_underline", type: "boolean", label: "Приховати підкреслення (underline)" })
                .registerSetting({ setting: "forumtaro-bbcodes.hide_link",      type: "boolean", label: "Приховати посилання (link)" })
                .registerSetting({ setting: "forumtaro-bbcodes.hide_image",     type: "boolean", label: "Приховати зображення (image)" })
                .registerSetting({ setting: "forumtaro-bbcodes.hide_code",      type: "boolean", label: "Приховати код (code)" })
                .registerSetting({ setting: "forumtaro-bbcodes.hide_quote",     type: "boolean", label: "Приховати цитату (quote)" })
                .registerSetting({ setting: "forumtaro-bbcodes.hide_strike",    type: "boolean", label: "Приховати закреслення (strike)" })
                .registerSetting({ setting: "forumtaro-bbcodes.hide_header",    type: "boolean", label: "Приховати заголовок (header)" })
                .registerSetting({ setting: "forumtaro-bbcodes.hide_list",      type: "boolean", label: "Приховати списки (list)" })
                .registerSetting({ setting: "forumtaro-bbcodes.hide_spoiler",   type: "boolean", label: "Приховати спойлер (spoiler)" })
                .registerSetting({ setting: "forumtaro-bbcodes.hide_mention",   type: "boolean", label: "Приховати згадування (mention)" })
                .registerSetting({ setting: "forumtaro-bbcodes.hide_preview",   type: "boolean", label: "Приховати попередній перегляд (preview)" });

            var initialBbcodes = ' . $customBbcodes . ';
            var bbcodes = JSON.parse(JSON.stringify(initialBbcodes));

            function save(successMsg) {
                app.request({
                    method: "POST",
                    url: app.forum.attribute("apiUrl") + "/forumtaro-bbcodes",
                    body: { bbcodes: bbcodes }
                }).then(function(data){
                    bbcodes = data.bbcodes;
                    render();
                    showMsg(successMsg || "Збережено!", false);
                }).catch(function(err){
                    console.error("Save error:", err);
                    showMsg("Помилка збереження: " + (err.message || "Невідома помилка"), true);
                });
            }

            function showMsg(text, isErr) {
                var el = document.getElementById("olleksi-msg");
                if (!el) return;
                el.textContent = text;
                el.style.color = isErr ? "#c0392b" : "#27ae60";
                el.style.display = "block";
                setTimeout(function(){ el.style.display = "none"; }, 3000);
            }

            function escHtml(s) {
                return String(s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");
            }

            function render() {
                var wrap = document.getElementById("olleksi-bbcode-list");
                if (!wrap) return;

                var rows = bbcodes.map(function(bb, i) {
                    return "<div style=\"display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid #eee\">" +
                        "<i class=\"fas " + escHtml(bb.icon) + "\" style=\"width:20px;text-align:center\"></i>" +
                        "<span style=\"flex:1;font-size:13px\">" + escHtml(bb.name) + "</span>" +
                        "<span style=\"font-size:11px;color:#888;flex:2\">" + escHtml(bb.tooltip) + "</span>" +
                        "<button class=\"Button Button--primary\" style=\"font-size:11px;padding:2px 8px\" onclick=\"olleksiEdit(" + i + ")\">Ред.</button>" +
                        "<button class=\"Button\" style=\"font-size:11px;padding:2px 8px\" onclick=\"olleksiToggle(" + i + ")\">" + (bb.visible ? "Видно" : "Прих.") + "</button>" +
                        "<button class=\"Button Button--danger\" style=\"font-size:11px;padding:2px 8px\" onclick=\"olleksiDelete(" + i + ")\">X</button>" +
                    "</div>";
                }).join("");

                wrap.innerHTML = rows || "<p style=\"color:#999;font-size:13px\">Немає кастомних BB-кодів</p>";
            }

            window.olleksiEdit = function(i) {
                var bb = bbcodes[i];
                document.getElementById("olleksi-f-name").value    = bb.name;
                document.getElementById("olleksi-f-icon").value    = bb.icon;
                document.getElementById("olleksi-f-tooltip").value = bb.tooltip;
                document.getElementById("olleksi-f-open").value    = bb.open;
                document.getElementById("olleksi-f-close").value   = bb.close;
                document.getElementById("olleksi-f-idx").value     = i;
                document.getElementById("olleksi-form-title").textContent = "Редагувати BB-код";
            };

            window.olleksiToggle = function(i) {
                bbcodes[i].visible = !bbcodes[i].visible;
                save("Видимість змінено");
            };

            window.olleksiDelete = function(i) {
                if (!confirm("Видалити \"" + bbcodes[i].name + "\"?")) return;
                bbcodes.splice(i, 1);
                save("Видалено");
            };

            var checkInterval = setInterval(function() {
                var settingsSection = document.querySelector(".ExtensionPage-settings");
                if (!settingsSection) return;
                if (document.getElementById("olleksi-bbcode-ui")) { clearInterval(checkInterval); return; }

                clearInterval(checkInterval);

                var ui = document.createElement("div");
                ui.id = "olleksi-bbcode-ui";
                ui.innerHTML = [
                    "<div style=\"margin-top:24px;border-top:2px solid #e0e0e0;padding-top:16px\">",
                    "<h3 style=\"font-size:15px;font-weight:600;margin-bottom:12px\">Кастомні BB-коди</h3>",
                    "<p id=\"olleksi-msg\" style=\"display:none;font-size:13px;margin-bottom:8px\"></p>",
                    "<div id=\"olleksi-bbcode-list\" style=\"margin-bottom:16px\"></div>",
                    "<div style=\"background:#f8f8f8;border-radius:6px;padding:14px\">",
                    "<p id=\"olleksi-form-title\" style=\"font-size:14px;font-weight:600;margin-bottom:10px\">Новий BB-код</p>",
                    "<input type=\"hidden\" id=\"olleksi-f-idx\" value=\"-1\">",
                    "<div style=\"display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px\">",
                    "<div><label style=\"font-size:12px;color:#555\">Назва кнопки</label>",
                    "<input id=\"olleksi-f-name\" class=\"FormControl\" placeholder=\"напр. Спойлер\" style=\"width:100%\"></div>",
                    "<div><label style=\"font-size:12px;color:#555\">FA іконка</label>",
                    "<input id=\"olleksi-f-icon\" class=\"FormControl\" placeholder=\"fa-star\" style=\"width:100%\"></div>",
                    "</div>",
                    "<div style=\"margin-bottom:8px\"><label style=\"font-size:12px;color:#555\">Підказка (tooltip)</label>",
                    "<input id=\"olleksi-f-tooltip\" class=\"FormControl\" placeholder=\"Текст при наведенні\" style=\"width:100%\"></div>",
                    "<div style=\"display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px\">",
                    "<div><label style=\"font-size:12px;color:#555\">Відкриваючий тег</label>",
                    "<input id=\"olleksi-f-open\" class=\"FormControl\" placeholder=\"[spoiler]\" style=\"width:100%\"></div>",
                    "<div><label style=\"font-size:12px;color:#555\">Закриваючий тег</label>",
                    "<input id=\"olleksi-f-close\" class=\"FormControl\" placeholder=\"[/spoiler]\" style=\"width:100%\"></div>",
                    "</div>",
                    "<div style=\"display:flex;gap:8px\">",
                    "<button class=\"Button Button--primary\" id=\"olleksi-save-btn\">Зберегти</button>",
                    "<button class=\"Button\" id=\"olleksi-reset-btn\">Скинути форму</button>",
                    "</div>",
                    "</div></div>"
                ].join("");

                settingsSection.appendChild(ui);
                render();

                document.getElementById("olleksi-save-btn").addEventListener("click", function() {
                    var idx     = parseInt(document.getElementById("olleksi-f-idx").value);
                    var name    = document.getElementById("olleksi-f-name").value.trim();
                    var icon    = document.getElementById("olleksi-f-icon").value.trim() || "fa-star";
                    var tooltip = document.getElementById("olleksi-f-tooltip").value.trim();
                    var open    = document.getElementById("olleksi-f-open").value;
                    var close   = document.getElementById("olleksi-f-close").value;

                    if (!name) { showMsg("Вкажіть назву кнопки", true); return; }

                    var entry = { name: name, icon: icon, tooltip: tooltip, open: open, close: close, visible: true };

                    if (idx >= 0 && idx < bbcodes.length) {
                        entry.visible = bbcodes[idx].visible;
                        bbcodes[idx] = entry;
                    } else {
                        bbcodes.push(entry);
                    }

                    save("Збережено!");
                    document.getElementById("olleksi-reset-btn").click();
                });

                document.getElementById("olleksi-reset-btn").addEventListener("click", function() {
                    document.getElementById("olleksi-f-idx").value = "-1";
                    document.getElementById("olleksi-f-name").value = "";
                    document.getElementById("olleksi-f-icon").value = "";
                    document.getElementById("olleksi-f-tooltip").value = "";
                    document.getElementById("olleksi-f-open").value = "";
                    document.getElementById("olleksi-f-close").value = "";
                    document.getElementById("olleksi-form-title").textContent = "Новий BB-код";
                });

            }, 300);

            if (window.m && window.m.redraw) window.m.redraw();

        } catch(e) { console.error("BBCodes admin error:", e); }
    }, 2000);
});
</script>';
        }),

    // ─── Settings defaults ────────────────────────────────────────────────────
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
