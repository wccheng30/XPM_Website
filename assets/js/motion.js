/* ==========================================================================
   XPM Motion layer
   Works alongside main.js: it never touches ids, form fields or main.js hooks.
   ========================================================================== */
(function () {
    'use strict';

    var root = document.documentElement;
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var isAdmin = document.body && document.body.getAttribute('data-motion') === 'admin';

    root.classList.add('js');
    window.__xpmMotion = true;

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    /* ---------- Scroll reveal ---------- */
    function initReveal() {
        document.querySelectorAll('[data-reveal-stagger]').forEach(function (group) {
            var type = group.getAttribute('data-reveal-stagger') || 'up';
            Array.prototype.forEach.call(group.children, function (child, i) {
                if (!child.hasAttribute('data-reveal')) child.setAttribute('data-reveal', type);
                child.style.setProperty('--i', i % 8);
            });
        });

        var targets = Array.prototype.filter.call(
            document.querySelectorAll('[data-reveal]'),
            function (el) { return !el.classList.contains('scroll-reveal'); }
        );

        if (reduceMotion || !('IntersectionObserver' in window)) {
            targets.forEach(function (el) { el.classList.add('is-visible'); });
            return;
        }

        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var el = entry.target;
                el.classList.add('is-visible');
                io.unobserve(el);
                el.addEventListener('transitionend', function clear(e) {
                    if (e.propertyName !== 'opacity') return;
                    el.style.removeProperty('--i');
                    el.removeEventListener('transitionend', clear);
                });
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

        targets.forEach(function (el) { io.observe(el); });

        document.querySelectorAll('.scroll-reveal').forEach(function (el) {
            el.addEventListener('transitionend', function clear(e) {
                if (e.propertyName !== 'opacity' || !el.classList.contains('revealed')) return;
                el.style.removeProperty('--i');
                el.removeEventListener('transitionend', clear);
            });
        });
    }

    /* Staggers main.js .scroll-reveal siblings so grids cascade */
    function staggerMainReveal() {
        var seen = new Set();
        document.querySelectorAll('.scroll-reveal').forEach(function (el) {
            var parent = el.parentElement;
            if (!parent || seen.has(parent)) return;
            seen.add(parent);
            var i = 0;
            Array.prototype.forEach.call(parent.children, function (child) {
                if (child.classList.contains('scroll-reveal')) child.style.setProperty('--i', i++ % 6);
            });
        });
    }

    /* ---------- Word-by-word headings ---------- */
    function initSplitWords() {
        if (reduceMotion) return;
        document.querySelectorAll('[data-split]').forEach(function (el) {
            var index = 0;
            var walk = function (node) {
                Array.prototype.slice.call(node.childNodes).forEach(function (child) {
                    if (child.nodeType === 3) {
                        var parts = child.textContent.split(/(\s+)/);
                        var frag = document.createDocumentFragment();
                        parts.forEach(function (part) {
                            if (!part) return;
                            if (/^\s+$/.test(part)) {
                                frag.appendChild(document.createTextNode(part));
                            } else {
                                var span = document.createElement('span');
                                span.className = 'word';
                                span.style.setProperty('--w', index++);
                                span.textContent = part;
                                frag.appendChild(span);
                            }
                        });
                        node.replaceChild(frag, child);
                    } else if (child.nodeType === 1 && child.tagName !== 'BR') {
                        walk(child);
                    }
                });
            };
            walk(el);
            el.classList.add('split-words');
        });
    }

    /* ---------- Counters for [data-count] ---------- */
    function initCounters() {
        var counters = document.querySelectorAll('[data-count]');
        if (!counters.length) return;

        var run = function (el) {
            var target = parseFloat(el.getAttribute('data-count'));
            var suffix = el.getAttribute('data-suffix') || '';
            var decimals = (el.getAttribute('data-count').split('.')[1] || '').length;
            if (isNaN(target)) return;
            if (reduceMotion) {
                el.textContent = target.toFixed(decimals) + suffix;
                return;
            }
            var start = null;
            var duration = 1600;
            var step = function (ts) {
                if (!start) start = ts;
                var p = Math.min((ts - start) / duration, 1);
                var eased = 1 - Math.pow(1 - p, 4);
                el.textContent = (target * eased).toFixed(decimals) + suffix;
                if (p < 1) requestAnimationFrame(step);
            };
            requestAnimationFrame(step);
        };

        if (!('IntersectionObserver' in window)) {
            counters.forEach(run);
            return;
        }

        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                run(entry.target);
                io.unobserve(entry.target);
            });
        }, { threshold: 0.6 });

        counters.forEach(function (el) { io.observe(el); });
    }

    /* ---------- Scroll progress + back to top ---------- */
    function initScrollChrome() {
        if (isAdmin) return;

        var bar = document.createElement('div');
        bar.className = 'scroll-progress';
        bar.setAttribute('aria-hidden', 'true');
        document.body.appendChild(bar);

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'back-to-top';
        btn.setAttribute('aria-label', 'Back to top');
        btn.innerHTML = '<i class="fas fa-arrow-up"></i>';
        btn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
        });
        document.body.appendChild(btn);

        var ticking = false;
        var update = function () {
            var max = document.documentElement.scrollHeight - window.innerHeight;
            var p = max > 0 ? window.scrollY / max : 0;
            bar.style.transform = 'scaleX(' + p.toFixed(4) + ')';
            btn.classList.toggle('is-visible', window.scrollY > 700);
            ticking = false;
        };
        window.addEventListener('scroll', function () {
            if (!ticking) {
                requestAnimationFrame(update);
                ticking = true;
            }
        }, { passive: true });
        update();
    }

    /* ---------- Button ripple ---------- */
    function initRipple() {
        if (reduceMotion) return;
        document.addEventListener('pointerdown', function (e) {
            var btn = e.target.closest('.btn, .btn-admin');
            if (!btn || btn.disabled) return;
            var rect = btn.getBoundingClientRect();
            var dot = document.createElement('span');
            dot.className = 'ripple';
            dot.style.left = (e.clientX - rect.left) + 'px';
            dot.style.top = (e.clientY - rect.top) + 'px';
            btn.appendChild(dot);
            setTimeout(function () { dot.remove(); }, 650);
        });
    }

    /* ---------- Page leave transition ---------- */
    function initPageTransitions() {
        if (reduceMotion) return;

        window.addEventListener('pageshow', function () {
            document.body.classList.remove('is-leaving');
        });

        document.addEventListener('click', function (e) {
            if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            var link = e.target.closest('a[href]');
            if (!link) return;

            var href = link.getAttribute('href');
            if (!href || href.charAt(0) === '#' || /^(mailto:|tel:|javascript:)/i.test(href)) return;
            if (link.target && link.target !== '_self') return;
            if (link.hasAttribute('download') || link.hasAttribute('data-no-transition')) return;
            if (/export-|\.(pdf|csv|xlsx?|zip|docx?)(\?|$)/i.test(href)) return;

            var url;
            try { url = new URL(link.href, window.location.href); } catch (err) { return; }
            if (url.origin !== window.location.origin) return;
            if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return;

            e.preventDefault();
            document.body.classList.add('is-leaving');
            setTimeout(function () { window.location.href = url.href; }, 200);
            setTimeout(function () { document.body.classList.remove('is-leaving'); }, 1800);
        });
    }

    /* ---------- Legal table of contents ---------- */
    function initToc() {
        var toc = document.querySelector('[data-toc]');
        var content = document.querySelector('[data-toc-source]');
        if (!toc || !content) return;

        var headings = content.querySelectorAll('h2');
        headings.forEach(function (h, i) {
            if (!h.id) h.id = 'section-' + (i + 1);
            var a = document.createElement('a');
            a.href = '#' + h.id;
            a.textContent = h.textContent.replace(/^\d+\.\s*/, '');
            toc.appendChild(a);
        });

        if (!('IntersectionObserver' in window)) return;
        var links = toc.querySelectorAll('a');
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                links.forEach(function (l) {
                    l.classList.toggle('is-active', l.getAttribute('href') === '#' + entry.target.id);
                });
            });
        }, { rootMargin: '-20% 0px -70% 0px' });
        headings.forEach(function (h) { io.observe(h); });
    }

    /* ---------- Admin: stagger rows + modal open animation ---------- */
    function initAdminNav() {
        var toggle = document.getElementById('navToggle');
        var tabs = document.getElementById('navTabs');
        if (!toggle || !tabs) return;

        var nav = toggle.closest('.admin-nav') || tabs.parentNode;
        var backdrop = document.createElement('div');
        backdrop.className = 'nav-backdrop';
        backdrop.setAttribute('aria-hidden', 'true');
        nav.appendChild(backdrop);

        toggle.setAttribute('role', 'button');
        toggle.setAttribute('tabindex', '0');
        toggle.setAttribute('aria-label', 'Toggle navigation');
        toggle.setAttribute('aria-controls', 'navTabs');

        var setOpen = function (open) {
            toggle.classList.toggle('active', open);
            tabs.classList.toggle('active', open);
            backdrop.classList.toggle('active', open);
            nav.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.body.style.overflow = open ? 'hidden' : '';
        };

        toggle.addEventListener('click', function () {
            setOpen(!tabs.classList.contains('active'));
        });
        toggle.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                setOpen(!tabs.classList.contains('active'));
            }
        });
        backdrop.addEventListener('click', function () { setOpen(false); });
        tabs.querySelectorAll('.nav-tab').forEach(function (link) {
            link.addEventListener('click', function () { setOpen(false); });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && tabs.classList.contains('active')) setOpen(false);
        });
        window.addEventListener('resize', function () {
            if (window.innerWidth > 768 && tabs.classList.contains('active')) setOpen(false);
        });

        var active = tabs.querySelector('.nav-tab.active');
        if (active) active.setAttribute('aria-current', 'page');

        var sentinel = document.querySelector('.admin-nav-sentinel');
        var headerH = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--admin-header-h'), 10) || 68;
        var markStuck = function () {
            if (window.innerWidth <= 768) {
                nav.classList.remove('is-stuck');
                return;
            }
            var stuck = sentinel.getBoundingClientRect().top < headerH;
            nav.classList.toggle('is-stuck', stuck);
        };
        if (sentinel) {
            if ('IntersectionObserver' in window) {
                var stuckObserver = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (window.innerWidth <= 768) {
                            nav.classList.remove('is-stuck');
                            return;
                        }
                        nav.classList.toggle('is-stuck', !entry.isIntersecting);
                    });
                }, { rootMargin: '-' + headerH + 'px 0px 0px 0px' });
                stuckObserver.observe(sentinel);
            } else {
                window.addEventListener('scroll', markStuck, { passive: true });
                markStuck();
            }
            window.addEventListener('resize', function () {
                if (window.innerWidth <= 768) nav.classList.remove('is-stuck');
            });
        }

    }

    function initBackToTop() {
        var topBtn = document.getElementById('adminBackToTop');
        if (!topBtn || topBtn.getAttribute('data-bound') === '1') return;
        topBtn.setAttribute('data-bound', '1');
        var toggleTop = function () {
            topBtn.hidden = window.scrollY < 400;
        };
        window.addEventListener('scroll', toggleTop, { passive: true });
        toggleTop();
        topBtn.addEventListener('click', function () {
            var behavior = reduceMotion ? 'auto' : 'smooth';
            window.scrollTo({ top: 0, behavior: behavior });
        });
    }

    function initAdmin() {
        if (!isAdmin) return;

        initBackToTop();
        initAdminNav();

        document.querySelectorAll('tbody, .table-body, .stats-grid, .key-metrics, .trainers-grid').forEach(function (group) {
            group.classList.add('stagger-rows');
            Array.prototype.forEach.call(group.children, function (child, i) {
                child.style.setProperty('--i', Math.min(i, 20));
            });
        });

        if (!('MutationObserver' in window)) return;
        var mo = new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                var el = m.target;
                if (m.attributeName !== 'style' && m.attributeName !== 'class') return;
                if (!el.classList || !el.classList.contains('modal')) return;
                var visible = getComputedStyle(el).display !== 'none';
                el.classList.toggle('is-open', visible);
            });
        });
        document.querySelectorAll('.modal').forEach(function (el) {
            mo.observe(el, { attributes: true, attributeFilter: ['style', 'class'] });
        });
    }

    function initNavToggleKeys() {
        var toggle = document.getElementById('nav-toggle');
        if (!toggle) return;
        toggle.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggle.click();
            }
        });
        // Pages that don't load main.js still need a working mobile menu and scrolled navbar.
        if (typeof window.initNavigation !== 'function') {
            var menu = document.getElementById('nav-menu');
            var navbar = document.getElementById('navbar');
            toggle.addEventListener('click', function () {
                toggle.classList.toggle('active');
                if (menu) menu.classList.toggle('active');
                document.body.style.overflow = menu && menu.classList.contains('active') ? 'hidden' : '';
            });
            if (menu) {
                menu.querySelectorAll('.nav-link').forEach(function (link) {
                    link.addEventListener('click', function () {
                        toggle.classList.remove('active');
                        menu.classList.remove('active');
                        document.body.style.overflow = '';
                    });
                });
            }
            if (navbar) {
                var onScroll = function () {
                    navbar.classList.toggle('scrolled', window.scrollY > 100);
                };
                window.addEventListener('scroll', onScroll, { passive: true });
                onScroll();
            }
        }
        toggle.addEventListener('click', function () {
            toggle.setAttribute('aria-expanded', toggle.classList.contains('active') ? 'true' : 'false');
        });
    }

    function initPixelMagnet() {
        if (reduceMotion) return;
        if (!window.matchMedia('(pointer: fine)').matches) return;
        if (!document.body) return;

        var canvas = document.createElement('canvas');
        canvas.className = 'pixel-magnet';
        canvas.setAttribute('aria-hidden', 'true');
        canvas.style.position = 'fixed';
        canvas.style.inset = '0';
        canvas.style.zIndex = '1500';
        canvas.style.width = '100%';
        canvas.style.height = '100%';
        canvas.style.pointerEvents = 'none';
        document.body.appendChild(canvas);
        var ctx = canvas.getContext('2d');
        if (!ctx) return;

        var count = 25;
        var seed = 1;
        function nextRand() {
            seed = (seed * 16807) % 2147483647;
            return (seed - 1) / 2147483646;
        }

        var squares = [];
        var i;
        for (i = 0; i < count; i++) {
            squares.push({
                angle: nextRand() * Math.PI * 2,
                orbit: 0.28 + nextRand() * 0.72,
                size: (0.45 + nextRand() * 1.5) * 0.5,
                progress: nextRand(),
                rate: (0.45 + nextRand() * 0.55) * 2
            });
        }

        var pointer = { x: 0, y: 0, speed: 0, seen: false };
        var focus = { x: 0, y: 0 };
        var strength = 0;
        var running = true;
        var last = 0;

        function resize() {
            var dpr = Math.min(window.devicePixelRatio || 1, 2);
            canvas.width = Math.max(1, Math.round(window.innerWidth * dpr));
            canvas.height = Math.max(1, Math.round(window.innerHeight * dpr));
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        }

        window.addEventListener('pointermove', function (e) {
            if (e.pointerType && e.pointerType !== 'mouse') return;
            var dx = e.clientX - pointer.x;
            var dy = e.clientY - pointer.y;
            if (!pointer.seen) {
                focus.x = e.clientX;
                focus.y = e.clientY;
                dx = 0;
                dy = 0;
                pointer.seen = true;
            }
            pointer.x = e.clientX;
            pointer.y = e.clientY;
            pointer.speed = Math.hypot(dx, dy);
        }, { passive: true });

        document.addEventListener('visibilitychange', function () {
            running = !document.hidden;
            if (running) requestAnimationFrame(frame);
        });
        window.addEventListener('resize', resize);

        function frame(now) {
            if (!running) return;
            var dt = Math.min(0.05, last ? (now - last) / 1000 : 0.016);
            last = now;

            var width = window.innerWidth;
            var height = window.innerHeight;
            var half = height * 0.5;
            var grid = Math.max(6, Math.round(Math.min(width, height) / 100));
            var target = Math.min(1, pointer.speed / 16);
            strength = target > strength ? target : strength * Math.exp(-1.2 * dt);
            pointer.speed *= Math.exp(-6 * dt);

            var follow = 1 - Math.exp(-10.5 * dt);
            if (pointer.seen) {
                focus.x += (pointer.x - focus.x) * follow;
                focus.y += (pointer.y - focus.y) * follow;
            }

            ctx.clearRect(0, 0, width, height);
            if (pointer.seen && strength > 0.02) {
                var hot = Math.min(1, target);
                var gray = Math.round(138 - hot * 121);
                squares.forEach(function (square) {
                    square.progress -= square.rate * dt;
                    if (square.progress <= 0) square.progress += 1;
                    var pull = square.progress * square.progress;
                    var radius = half * square.orbit * pull;
                    var x = focus.x + Math.cos(square.angle) * radius;
                    var y = focus.y + Math.sin(square.angle) * radius;
                    x = Math.round(x / grid) * grid;
                    y = Math.round(y / grid) * grid;
                    var alpha = strength * Math.min(1, square.progress * 4);
                    if (alpha < 0.03) return;
                    var size = grid * square.size;
                    ctx.globalAlpha = alpha;
                    ctx.fillStyle = 'rgb(' + gray + ',' + gray + ',' + gray + ')';
                    ctx.fillRect(x - size / 2, y - size / 2, size, size);
                });
                ctx.globalAlpha = 1;
            }
            requestAnimationFrame(frame);
        }

        resize();
        requestAnimationFrame(frame);
    }

    ready(function () {
        initNavToggleKeys();
        initSplitWords();
        initReveal();
        initCounters();
        initScrollChrome();
        initRipple();
        initPageTransitions();
        initToc();
        initAdmin();
        initPixelMagnet();
        // main.js adds .scroll-reveal on DOMContentLoaded; run after it
        setTimeout(staggerMainReveal, 0);
    });

    setTimeout(function () {
        if (!('IntersectionObserver' in window)) root.classList.add('reveal-fallback');
    }, 3000);
})();
