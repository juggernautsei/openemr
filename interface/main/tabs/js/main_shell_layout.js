/**
 * main_shell_layout.js
 *
 * Progressive enhancement for vertical main shell:
 * - accordion open/close for nested menu sections (keeps menuActionClick for leaves)
 * - optional mobile sidebar collapse
 *
 * @package OpenEMR
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */
(function (window, document, $) {
    'use strict';

    function isVerticalShell() {
        return !!document.querySelector('#mainBox.main-shell--vertical');
    }

    /**
     * Toggle a menu section open state. Nested sections supported.
     * @param {HTMLElement} section
     * @param {boolean} [force]
     */
    function setSectionOpen(section, force) {
        if (!section) {
            return;
        }
        const open = typeof force === 'boolean' ? force : !section.classList.contains('is-open');
        section.classList.toggle('is-open', open);
        const toggle = section.querySelector(':scope > .menuLabel.dropdown-toggle, :scope > .menuLabel');
        if (toggle) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }

    function closeSiblingSections(section) {
        const parent = section.parentElement;
        if (!parent) {
            return;
        }
        parent.querySelectorAll(':scope > .menuSection.is-open, :scope > div > .menuSection.is-open').forEach(function (sib) {
            if (sib !== section) {
                setSectionOpen(sib, false);
            }
        });
        // Also handle KO root wrappers: direct child divs containing .menuSection
        Array.prototype.forEach.call(parent.children, function (child) {
            if (child === section) {
                return;
            }
            if (child.classList && child.classList.contains('menuSection') && child !== section) {
                setSectionOpen(child, false);
            }
            const nested = child.querySelector && child.querySelector(':scope > .menuSection');
            if (nested && nested !== section && child.contains(section) === false) {
                // only close peer roots
            }
        });
    }

    function onMenuClick(evt) {
        if (!isVerticalShell()) {
            return;
        }
        const label = evt.target.closest('.appMenu--vertical .menuSection > .menuLabel');
        if (!label) {
            return;
        }
        // Leaf labels use menuActionClick; only intercept section headers with children
        if (label.classList.contains('menuLabel--item') && !label.classList.contains('menuLabel--section')) {
            return;
        }
        const section = label.closest('.menuSection');
        if (!section) {
            return;
        }
        const entries = section.querySelector(':scope > .menuEntries, :scope > ul.menuEntries');
        if (!entries) {
            return;
        }
        // Header: prevent bootstrap dropdown / navigation; accordion only
        evt.preventDefault();
        evt.stopPropagation();
        const willOpen = !section.classList.contains('is-open');
        if (willOpen) {
            // close peer sections at same list level
            const listParent = section.parentElement;
            if (listParent) {
                listParent.querySelectorAll('.menuSection.is-open').forEach(function (openSec) {
                    if (openSec !== section && !section.contains(openSec) && !openSec.contains(section)) {
                        // same depth peers only
                        if (openSec.parentElement === listParent) {
                            setSectionOpen(openSec, false);
                        }
                    }
                });
            }
        }
        setSectionOpen(section, willOpen);
    }

    function initAccordion() {
        const root = document.getElementById('mainMenu');
        if (!root || root.dataset.shellAccordion === '1') {
            return;
        }
        root.dataset.shellAccordion = '1';
        root.addEventListener('click', onMenuClick, true);
    }

    function setSidebarOpen(box, open) {
        box.classList.toggle('main-shell--sidebar-open', open);
        box.classList.toggle('main-shell--sidebar-collapsed', !open);
        document.querySelectorAll('.main-shell__menu-toggle').forEach(function (btn) {
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    function initMobileToggle() {
        const box = document.getElementById('mainBox');
        if (!box || box.dataset.shellToggle === '1') {
            return;
        }
        box.dataset.shellToggle = '1';

        // Mobile starts closed; desktop keeps rail visible via CSS
        if (window.matchMedia && window.matchMedia('(max-width: 768px)').matches) {
            setSidebarOpen(box, false);
        } else {
            setSidebarOpen(box, true);
        }

        document.querySelectorAll('.main-shell__menu-toggle').forEach(function (btn) {
            btn.addEventListener('click', function (evt) {
                evt.preventDefault();
                const open = !box.classList.contains('main-shell--sidebar-open');
                setSidebarOpen(box, open);
            });
        });

        // Tap dimmed workspace overlay to close drawer
        box.addEventListener('click', function (evt) {
            if (!box.classList.contains('main-shell--sidebar-open')) {
                return;
            }
            if (window.matchMedia && !window.matchMedia('(max-width: 768px)').matches) {
                return;
            }
            // pseudo-element overlay sits over workspace; detect clicks on workspace chrome only when target is workspace itself
            if (evt.target === box.querySelector('.main-shell__workspace')) {
                setSidebarOpen(box, false);
            }
        });

        window.addEventListener('resize', function () {
            if (!window.matchMedia) {
                return;
            }
            if (window.matchMedia('(max-width: 768px)').matches) {
                // keep current mobile state
            } else {
                setSidebarOpen(box, true);
            }
        });
    }

    function boot() {
        if (!isVerticalShell()) {
            return;
        }
        initAccordion();
        initMobileToggle();
        // Disable bootstrap dropdown hover interference on vertical headers
        if ($ && $.fn && $.fn.dropdown) {
            $('#mainMenu .dropdown-toggle').each(function () {
                $(this).attr('data-toggle', null);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    // KO renders menu async-ish after applyBindings; re-run shortly
    window.setTimeout(boot, 0);
    window.setTimeout(boot, 250);
})(window, document, window.jQuery);
