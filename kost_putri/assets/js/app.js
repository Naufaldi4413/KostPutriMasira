document.addEventListener('DOMContentLoaded', () => {
    const navbar = document.getElementById('site-navbar');
    const onScroll = () => navbar?.classList.toggle('navbar-scrolled', window.scrollY > 20);
    window.addEventListener('scroll', onScroll, {passive:true});
    onScroll();

    const navToggle   = document.getElementById('navToggle');
    const navMenu     = document.getElementById('navMenu');
    const navBackdrop = document.getElementById('navBackdrop');

    const toggleMenu = (force) => {
        const open = force !== undefined ? force : !navMenu?.classList.contains('is-open');
        navMenu?.classList.toggle('is-open', open);
        navBackdrop?.classList.toggle('is-open', open);
        navToggle?.setAttribute('aria-expanded', open);
        document.body.style.overflow = open ? 'hidden' : '';
    };
    navToggle?.addEventListener('click', () => toggleMenu());
    navBackdrop?.addEventListener('click', () => toggleMenu(false));
    const navCloseBtn = document.getElementById('navCloseBtn');
    navCloseBtn?.addEventListener('click', () => toggleMenu(false));
    document.querySelectorAll('.nav-links .nav-link').forEach(l => {
        l.addEventListener('click', () => { if (window.innerWidth <= 1024) toggleMenu(false); });
    });

    const sections = document.querySelectorAll('section[id]');
    const navLinks = document.querySelectorAll('.nav-links .nav-link');
    window.addEventListener('scroll', () => {
        const pos = window.scrollY + 140;
        sections.forEach(sec => {
            if (pos >= sec.offsetTop && pos < sec.offsetTop + sec.offsetHeight) {
                navLinks.forEach(l => l.classList.toggle('active', l.getAttribute('href') === '#' + sec.id));
            }
        });
    }, {passive:true});

    document.querySelectorAll('a[href^="#"]').forEach(a => {
        a.addEventListener('click', e => {
            const id = a.getAttribute('href');
            if (!id || id === '#') return;
            const t = document.querySelector(id);
            if (!t) return;
            e.preventDefault();
            window.scrollTo({ top: t.getBoundingClientRect().top + pageYOffset - (navbar?.offsetHeight||80) - 5, behavior:'smooth' });
        });
    });
});

// Carousel daftar kamar: tombol panah + geser dengan mouse/sentuh
document.addEventListener('DOMContentLoaded', () => {
    const track = document.getElementById('roomsTrack');
    const wrap  = document.getElementById('roomsCarousel');
    if (!track || !wrap) return;
    const prev = document.getElementById('roomsPrev');
    const next = document.getElementById('roomsNext');

    const step = () => {
        const card = track.querySelector('.room-card');
        if (!card) return track.clientWidth;
        const gap = parseFloat(getComputedStyle(track).columnGap || getComputedStyle(track).gap) || 24;
        return card.getBoundingClientRect().width + gap;
    };
    const update = () => {
        const max = track.scrollWidth - track.clientWidth - 8;
        if (prev) prev.disabled = track.scrollLeft <= 8;
        if (next) next.disabled = track.scrollLeft >= max;
        wrap.classList.toggle('carousel-single', max <= 0);
    };
    prev?.addEventListener('click', () => track.scrollBy({ left: -step(), behavior: 'smooth' }));
    next?.addEventListener('click', () => track.scrollBy({ left:  step(), behavior: 'smooth' }));
    track.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    track.addEventListener('keydown', e => {
        if (e.key === 'ArrowRight') { e.preventDefault(); track.scrollBy({ left: step(), behavior: 'smooth' }); }
        if (e.key === 'ArrowLeft')  { e.preventDefault(); track.scrollBy({ left: -step(), behavior: 'smooth' }); }
    });

    // Drag dengan mouse (desktop)
    let down = false, startX = 0, startLeft = 0, moved = false;
    track.addEventListener('mousedown', e => {
        if (e.target.closest('a,button')) return;
        down = true; moved = false; startX = e.pageX; startLeft = track.scrollLeft;
    });
    window.addEventListener('mousemove', e => {
        if (!down) return;
        const dx = e.pageX - startX;
        if (Math.abs(dx) > 5) { moved = true; track.classList.add('is-dragging'); }
        if (moved) track.scrollLeft = startLeft - dx;
    });
    window.addEventListener('mouseup', () => {
        if (!down) return;
        down = false;
        if (moved) {
            track.classList.remove('is-dragging');
            const s = step();
            track.scrollTo({ left: Math.round(track.scrollLeft / s) * s, behavior: 'smooth' });
        }
    });
    track.querySelectorAll('img').forEach(i => i.addEventListener('dragstart', e => e.preventDefault()));
    update();
    setTimeout(update, 400);
});
