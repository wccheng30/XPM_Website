// ===== DOM ELEMENTS =====
const navbar = document.getElementById('navbar');
const navMenu = document.getElementById('nav-menu');
const navToggle = document.getElementById('nav-toggle');
const contactForm = document.getElementById('contactForm');
const jobsGrid = document.getElementById('jobs-grid');

// ===== NAVIGATION FUNCTIONALITY =====
function initNavigation() {
    // Mobile menu toggle
    navToggle.addEventListener('click', () => {
        navMenu.classList.toggle('active');
        navToggle.classList.toggle('active');
        
        // Prevent body scroll when menu is open (but allow menu to scroll)
        if (navMenu.classList.contains('active')) {
            document.body.style.overflow = 'hidden';
            document.body.style.position = 'fixed';
            document.body.style.width = '100%';
            document.body.style.height = '100%';
            
            // Ensure mobile touch scrolling works
            navMenu.style.touchAction = 'pan-y';
            navMenu.style.overscrollBehavior = 'contain';
            
            // Check if menu needs scrolling and show/hide scroll hint
            setTimeout(() => {
                checkMenuScrollability();
            }, 300);
        } else {
            document.body.style.overflow = '';
            document.body.style.position = '';
            document.body.style.width = '';
            document.body.style.height = '';
            navMenu.style.touchAction = '';
            navMenu.style.overscrollBehavior = '';
        }
    });
    
    // Function to check if mobile menu needs scrolling
    function checkMenuScrollability() {
        if (navMenu.scrollHeight > navMenu.clientHeight) {
            navMenu.classList.add('scrollable');
        } else {
            navMenu.classList.remove('scrollable');
        }
    }
    
    // Handle menu scroll events
    navMenu.addEventListener('scroll', () => {
        // Hide scroll hint when user starts scrolling
        if (navMenu.scrollTop > 10) {
            navMenu.classList.add('scrolled');
        } else {
            navMenu.classList.remove('scrolled');
        }
    });

    // Close mobile menu when clicking on a link
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            navMenu.classList.remove('active');
            navToggle.classList.remove('active');
            document.body.style.overflow = '';
            document.body.style.position = '';
            document.body.style.width = '';
        });
    });

    // Enhanced navbar scroll effect
    let lastScrollY = window.scrollY;
    window.addEventListener('scroll', () => {
        const currentScrollY = window.scrollY;
        
        // Add scrolled class for styling
        if (currentScrollY > 100) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
        
        // Parallax effect for navbar
        if (currentScrollY > lastScrollY && currentScrollY > 200) {
            navbar.style.transform = 'translateY(-100%)';
        } else {
            navbar.style.transform = 'translateY(0)';
        }
        
        lastScrollY = currentScrollY;
    });

    // Active navigation state management
    function updateActiveNavLink() {
        const sections = document.querySelectorAll('section[id]');
        const navLinks = document.querySelectorAll('.nav-link');
        
        // Check if we're on a specific page
        const currentPage = window.location.pathname.split('/').pop();
        
        navLinks.forEach(link => {
            link.classList.remove('active');
            const href = link.getAttribute('href');
            
            // Check for external page links first
            if (href.includes('.php') || href.includes('.html')) {
                if (href.includes(currentPage) || (currentPage === '' && href.includes('index.html'))) {
                    link.classList.add('active');
                }
            } else if (href.startsWith('#')) {
                // Handle anchor links for homepage
                let currentSection = '';
                sections.forEach(section => {
                    const sectionTop = section.offsetTop - 100;
                    const sectionHeight = section.offsetHeight;
                    if (window.scrollY >= sectionTop && window.scrollY < sectionTop + sectionHeight) {
                        currentSection = section.getAttribute('id');
                    }
                });
                
                if (href === `#${currentSection}`) {
                    link.classList.add('active');
                }
            }
        });
    }

    // Update active link on scroll
    window.addEventListener('scroll', debounce(updateActiveNavLink, 100));

    // Smooth scrolling for navigation links (only for anchor links)
    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            const targetId = link.getAttribute('href');
            
            // Check if it's an anchor link (starts with #)
            if (targetId.startsWith('#')) {
                e.preventDefault();
                const targetSection = document.querySelector(targetId);
                
                if (targetSection) {
                    const offsetTop = targetSection.offsetTop - 80;
                    window.scrollTo({
                        top: offsetTop,
                        behavior: 'smooth'
                    });
                }
            }
            // For external page links (like programmes.php, jobs.php), let them work normally
        });
    });

    // Add keyboard navigation support
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            navMenu.classList.remove('active');
            navToggle.classList.remove('active');
            document.body.style.overflow = '';
            document.body.style.position = '';
            document.body.style.width = '';
        }
    });
}

// ===== SCROLL ANIMATIONS =====
function initScrollAnimations() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('revealed');
                
                // Add staggered animation for child elements
                const children = entry.target.querySelectorAll('.animate-on-scroll');
                children.forEach((child, index) => {
                    setTimeout(() => {
                        child.classList.add('animated');
                    }, index * 100);
                });
            }
        });
    }, observerOptions);

    // Observe elements for scroll animations
    const scrollElements = document.querySelectorAll('.service-card, .facility-card, .testimonial-card, .feature, .stat-card, .timeline-item, .expertise-card, .achievement-item, .achievement-card, .profile-card, .story-container, .programme-card');
    scrollElements.forEach(el => {
        el.classList.add('scroll-reveal');
        observer.observe(el);
    });
}

// ===== FOUNDER PHASE 2 INTERACTIVITY =====
function initFounderInteractivity() {
    const section = document.querySelector('.founder-visionary');
    if (!section) return;

    // Respect reduced motion
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (prefersReducedMotion) {
        // Disable interactive effects when reduced motion is requested
        section.style.setProperty('--spotlight-x', '50%');
        section.style.setProperty('--spotlight-y', '50%');
        return;
    }

    // Spotlight cursor effect
    section.addEventListener('mousemove', (e) => {
        const rect = section.getBoundingClientRect();
        const x = ((e.clientX - rect.left) / rect.width) * 100 + '%';
        const y = ((e.clientY - rect.top) / rect.height) * 100 + '%';
        section.style.setProperty('--spotlight-x', x);
        section.style.setProperty('--spotlight-y', y);
    }, { passive: true });

    // Fallback: animate glow on idle so it’s visible even if mouse not moved
    let idle = true;
    let t = 0;
    function idleTick() {
        if (!idle) return;
        t += 0.01;
        const x = 50 + Math.sin(t) * 20; // 30%..70%
        const y = 50 + Math.cos(t * 0.8) * 10; // 40%..60%
        section.style.setProperty('--spotlight-x', x + '%');
        section.style.setProperty('--spotlight-y', y + '%');
        requestAnimationFrame(idleTick);
    }
    idleTick();
    section.addEventListener('mousemove', () => { idle = false; }, { once: true });

    // Parallax background for particles and shapes
    const particles = section.querySelectorAll('.particle');
    const shapes = section.querySelectorAll('.shape');
    const parallax = (targets, factorX, factorY) => {
        window.addEventListener('mousemove', (e) => {
            const cx = (e.clientX / window.innerWidth) - 0.5;
            const cy = (e.clientY / window.innerHeight) - 0.5;
            targets.forEach((el, i) => {
                const depth = (i + 1) / targets.length;
                const tx = cx * factorX * depth * 20;
                const ty = cy * factorY * depth * 20;
                el.style.transform = `translate(${tx}px, ${ty}px)`;
            });
        });
    };
    parallax(particles, -1, -1);
    parallax(shapes, 1, 1);
}

// ===== FOUNDER PHASE 4: Dynamic content loader =====
async function initFounderContent() {
    const section = document.querySelector('.founder-visionary');
    if (!section) return;

    const defaultData = {
        name: 'iCoL Chua',
        role: 'Founder & HR Specialist',
        stats: [
            { label: 'Years Experience', value: '20+' },
            { label: 'Companies Served', value: '500+' },
            { label: 'Professionals Trained', value: '1000+' }
        ],
        achievements: [
            { icon: 'fas fa-award', title: 'Certified Trainer', text: 'Nationally recognized training certifications' },
            { icon: 'fas fa-handshake', title: 'Authority Relations', text: 'Strong rapport with KWSP, PERKESO, LHDN, JTK' },
            { icon: 'fas fa-chart-line', title: 'Business Growth', text: 'Led company expansion during challenging times' },
            { icon: 'fas fa-users-cog', title: 'Team Leadership', text: 'Built and managed dedicated expert teams' }
        ],
        story: [
            'iCoL Chua has a personality that empowers optimised employment and HR management. With over 20 years in the industry, iCoL possesses exemplary experience in employee welfare, recruitment, payroll, training, and employment-optimised management.',
            'She maintains strong rapport with local authorities such as KWSP, PERKESO, LHDN, JTK, JKKP and others allowing her to handle HR matters efficiently and in line with client expectations.',
            'As a certified trainer, iCoL brings deep expertise in topics such as Employee Resolution, Conflict Management, Profile Regulation, and other critical HR areas. Through Xpress Points Training & Development division, she continues to lead impactful, certified programs that align with national standards.'
        ]
    };

    const src = section.getAttribute('data-founder-src');

    const applyData = (data) => {
        const nameEl = section.querySelector('.js-founder-name');
        const roleEl = section.querySelector('.js-founder-role');
        if (nameEl && data.name) nameEl.textContent = data.name;
        if (roleEl && data.role) roleEl.textContent = data.role;

        const statsWrap = section.querySelector('.js-founder-stats');
        if (statsWrap && Array.isArray(data.stats)) {
            statsWrap.innerHTML = data.stats.map((s) => {
                const value = s.value;
                const isStringValue = typeof value === 'string' && value.includes('+');
                let targetValue, suffix = '';
                
                if (isStringValue) {
                    // Extract number and suffix from strings like "500+" or "20+"
                    const match = value.match(/^(\d+)(.*)$/);
                    if (match) {
                        targetValue = parseInt(match[1]);
                        suffix = match[2]; // This will be "+"
                    } else {
                        targetValue = value; // Fallback for non-numeric strings
                    }
                } else {
                    targetValue = Number(value) || 0;
                }
                
                return `
                    <div class="stat-item">
                        <div class="stat-number founder-stat-number" data-target="${targetValue}" ${suffix ? `data-suffix="${suffix}"` : ''}>0</div>
                        <div class="stat-label founder-stat-label">${s.label || ''}</div>
                        <div class="stat-glow"></div>
                    </div>
                `;
            }).join('');
            initFounderCounters();
        }

        const achWrap = section.querySelector('#achievement-cards');
        if (achWrap && Array.isArray(data.achievements)) {
            achWrap.innerHTML = data.achievements.map((a, i) => `
                <div class="achievement-card card-${i+1}">
                    <div class="card-icon"><i class="${a.icon || 'fas fa-star'}"></i></div>
                    <div class="card-content">
                        <h4>${a.title || ''}</h4>
                        <p>${a.text || ''}</p>
                    </div>
                    <div class="card-glow"></div>
                </div>
            `).join('');
        }

        const storyWrap = section.querySelector('.js-story-content');
        if (storyWrap && Array.isArray(data.story)) {
            storyWrap.innerHTML = data.story.map(p => `
                <div class="story-paragraph"><p>${p}</p></div>
            `).join('');
        }
    };

    if (!src) {
        applyData(defaultData);
        return;
    }

    try {
        const res = await fetch(src, { cache: 'no-store' });
        if (!res.ok) throw new Error('Failed to load founder data');
        const data = await res.json();
        applyData(data || defaultData);
    } catch (e) {
        // Use default content if loading fails
    }
}

// ===== FOUNDER PHASE 4: Video lightbox =====
function initFounderStoryVideo() {
    const btn = document.querySelector('.watch-video');
    if (!btn) return;
    
    btn.addEventListener('click', () => {
        const url = btn.getAttribute('data-video-url');
        
        if (!url) {
            console.error('No video URL found');
            return;
        }
        
        const overlay = document.createElement('div');
        overlay.className = 'video-lightbox';

        // If MP4, use <video>; otherwise iframe
        const isMp4 = /\.mp4(\?|$)/i.test(url);
        
        const autoplayUrl = !isMp4 ? (url.includes('?') ? `${url}&autoplay=1` : `${url}?autoplay=1`) : url;
        const content = isMp4
            ? `<video class="video-frame" controls autoplay playsinline preload="metadata" muted>
                <source src="${autoplayUrl}" type="video/mp4">
                Your browser does not support the video tag.
               </video>`
            : `<iframe class="video-frame" src="${autoplayUrl}" allow="autoplay; encrypted-media" allowfullscreen></iframe>`;

        overlay.innerHTML = `
            <button class="video-close" aria-label="Close video">&times;</button>
            ${content}
        `;
        document.body.appendChild(overlay);
        
        if (isMp4) {
            const video = overlay.querySelector('video');
            if (video && typeof video.play === 'function') {
                video.play().catch((error) => {
                    console.error('Video play failed:', error);
                });
            }
        }
        
        overlay.querySelector('.video-close').addEventListener('click', () => {
            document.body.removeChild(overlay);
        });
        
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                document.body.removeChild(overlay);
            }
        });
    });
}

// ===== FOUNDER PHASE 4: Story scroll progress =====
function initStoryProgress() {
    const story = document.querySelector('.founder-story-section .story-container');
    if (!story) return;
    const bar = story.querySelector('.story-progress-bar');
    if (!bar) return;

    function update() {
        const rect = story.getBoundingClientRect();
        const vh = window.innerHeight || 1;
        const total = rect.height + vh; // enter to exit
        const seen = Math.min(Math.max(vh - rect.top, 0), total);
        const pct = Math.max(0, Math.min(100, (seen / total) * 100));
        bar.style.width = pct + '%';
    }
    update();
    window.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
}

// ===== FORM HANDLING =====
function requireContactLogin() {
    if (!contactForm) return;
    fetch('api/nav-user.php', { credentials: 'same-origin' })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (data && data.loggedIn) return;
            var next = encodeURIComponent('index.html#contact');
            contactForm.classList.add('form-locked');
            contactForm.querySelectorAll('input, select, textarea, button').forEach(function (field) {
                field.disabled = true;
            });
            var note = document.createElement('div');
            note.className = 'guest-gate';
            note.setAttribute('role', 'status');
            note.innerHTML = '<div class="guest-gate-icon" aria-hidden="true"><i class="fas fa-lock"></i></div>' +
                '<div><strong>Log in or create an account first.</strong>' +
                '<p>You can look through this form, but the fields stay locked until you sign in. Then you can fill in the details and send a message.</p>' +
                '<div class="guest-gate-actions">' +
                '<a class="btn btn-primary" href="login.php?next=' + next + '">Log in</a>' +
                '<a class="btn btn-outline" href="register.php?next=' + next + '">Create an account</a>' +
                '</div></div>';
            contactForm.parentElement.insertBefore(note, contactForm);
        })
        .catch(function () {});
}

function initFormHandling() {
    if (contactForm) {
        requireContactLogin();
        contactForm.addEventListener('submit', handleFormSubmit);
        
        // Add real-time validation
        const inputs = contactForm.querySelectorAll('input, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', validateField);
            input.addEventListener('input', clearFieldError);
        });
        
        // Custom service field handling
        const interestSelect = document.getElementById('interest');
        const customServiceField = document.getElementById('custom-service-field');
        
        if (interestSelect && customServiceField) {
            interestSelect.addEventListener('change', function() {
                if (this.value === 'custom') {
                    customServiceField.style.display = 'block';
                    document.getElementById('custom-service-details').required = true;
                } else {
                    customServiceField.style.display = 'none';
                    document.getElementById('custom-service-details').required = false;
                    document.getElementById('custom-service-details').value = '';
                }
            });
        }
    }
}

function fieldErrorParent(field) {
    if (field.closest('.phone-field')) {
        return field.closest('.form-group') || field.parentNode;
    }
    return field.parentNode;
}

function validateField(e) {
    const field = e.target;
    if (field.name && field.name.endsWith('_code')) {
        return;
    }
    const value = field.value.trim();
    const fieldName = field.name;
    
    // Remove existing error
    clearFieldError(e);
    
    // Validation rules
    let isValid = true;
    let errorMessage = '';
    
    switch (fieldName) {
        case 'name':
            if (value.length < 2) {
                isValid = false;
                errorMessage = 'Name must be at least 2 characters long';
            }
            break;
        case 'email':
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                isValid = false;
                errorMessage = 'Please enter a valid email address';
            }
            break;
        case 'phone':
            const cleanedPhone = value.replace(/\D/g, '');
            if (value && !/^\d{6,15}$/.test(cleanedPhone)) {
                isValid = false;
                errorMessage = 'Please enter a valid phone number';
            }
            break;
        case 'message':
            if (value.length < 10) {
                isValid = false;
                errorMessage = 'Message must be at least 10 characters long';
            }
            break;
        case 'custom-service-details':
            // Only validate if custom service is selected and field is visible
            const interestSelect = document.getElementById('interest');
            const customServiceField = document.getElementById('custom-service-field');
            if (interestSelect && interestSelect.value === 'custom' && 
                customServiceField && customServiceField.style.display !== 'none') {
                if (value.length < 10) {
                    isValid = false;
                    errorMessage = 'Please provide detailed information about your custom service requirements (at least 10 characters)';
                }
            }
            break;
    }
    
    if (!isValid) {
        showFieldError(field, errorMessage);
    }
}

function showFieldError(field, message) {
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.textContent = message;
    errorDiv.style.cssText = `
        color: #b91c1c;
        font-size: 0.875rem;
        margin-top: 0.25rem;
    `;
    
    fieldErrorParent(field).appendChild(errorDiv);
    field.style.borderColor = '#b91c1c';
}

function clearFieldError(e) {
    const field = e.target;
    const errorDiv = fieldErrorParent(field).querySelector('.field-error');
    if (errorDiv) {
        errorDiv.remove();
    }
    field.style.borderColor = '';
}

async function handleFormSubmit(e) {
    e.preventDefault();

    if (contactForm && contactForm.classList.contains('form-locked')) {
        showNotification('Log in or create an account before you send a message.', 'error');
        return;
    }
    
    if (!contactForm) {
        showNotification('Form not found. Please refresh the page.', 'error');
        return;
    }
    
    const formData = new FormData(contactForm);
    const submitButton = contactForm.querySelector('button[type="submit"]');
    
    if (!submitButton) {
        showNotification('Submit button not found. Please refresh the page.', 'error');
        return;
    }
    
    const originalText = submitButton.textContent;
    
    // Validate all fields before submission
    const inputs = contactForm.querySelectorAll('input, textarea, select');
    let isValid = true;
    
    inputs.forEach(input => {
        validateField({ target: input });
        if (fieldErrorParent(input).querySelector('.field-error')) {
            isValid = false;
        }
    });
    
    // Special validation for custom service field if visible
    const customServiceField = document.getElementById('custom-service-field');
    const customServiceDetails = document.getElementById('custom-service-details');
    if (customServiceField && customServiceField.style.display !== 'none' && customServiceDetails) {
        validateField({ target: customServiceDetails });
        if (customServiceDetails.parentNode.querySelector('.field-error')) {
            isValid = false;
        }
    }
    
    if (!isValid) {
        showNotification('Please correct the errors before submitting.', 'error');
        return;
    }
    
    // Show loading state
    submitButton.textContent = 'Sending...';
    submitButton.disabled = true;
    contactForm.classList.add('loading');
    
    try {
        const response = await fetch('api/contact.php', {
            method: 'POST',
            body: formData
        });
        
        if (response.status === 401) {
            showNotification('Log in or create an account before you send a message.', 'error');
            return;
        }

        if (response.ok) {
            const responseData = await response.json();
            
            if (responseData.success) {
                showNotification('Thank you! Your message has been sent successfully.', 'success');
                contactForm.reset();
                
                // Reset custom service field visibility
                const customServiceField = document.getElementById('custom-service-field');
                if (customServiceField) {
                    customServiceField.style.display = 'none';
                }
            } else {
                throw new Error(responseData.message || 'Server returned an error');
            }
        } else {
            const errorText = await response.text();
            console.error('Server error response:', errorText);
            throw new Error(`Server error: ${response.status} ${response.statusText}`);
        }
    } catch (error) {
        console.error('Form submission error:', error);
        showNotification(`Sorry, there was an error sending your message: ${error.message}`, 'error');
    } finally {
        // Reset button state
        submitButton.textContent = originalText;
        submitButton.disabled = false;
        contactForm.classList.remove('loading');
    }
}

// ===== NOTIFICATION SYSTEM =====
function showNotification(message, type = 'info') {
    // Create a simple, working notification
    const notification = document.createElement('div');
    
    // Set background color based on type
    let bgColor = '#111111';
    if (type === 'success') bgColor = '#15803d';
    if (type === 'error') bgColor = '#b91c1c';
    
    notification.innerHTML = `
        <div style="
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${bgColor};
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            z-index: 999999;
            max-width: 400px;
            font-family: inherit;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            transform: translateX(0);
            opacity: 1;
            visibility: visible;
        ">
            <span style="flex: 1; font-weight: 500; line-height: 1.4;">${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" style="
                background: rgba(255, 255, 255, 0.2);
                border: none;
                color: white;
                font-size: 1.5rem;
                font-weight: bold;
                width: 28px;
                height: 28px;
                border-radius: 50%;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.3s ease;
                flex-shrink: 0;
                line-height: 1;
                padding: 0;
                margin: 0;
                outline: none;
            ">&times;</button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (document.body.contains(notification)) {
            notification.style.transform = 'translateX(100%)';
            notification.style.opacity = '0';
            setTimeout(() => {
                if (document.body.contains(notification)) {
                    document.body.removeChild(notification);
                }
            }, 300);
        }
    }, 5000);
}

// ===== JOBS LOADING =====
function loadJobs() {
    if (!jobsGrid) return;
    
    // Check if there are already PHP-generated job cards
    const existingJobCards = document.querySelectorAll('.programme-card');
    if (existingJobCards.length > 0) {
        // PHP content already exists, don't override it
        return;
    }
    
    // Sample job data - in a real application, this would come from an API
    const jobs = [
        {
            title: 'Senior HR Trainer',
            location: 'Remote / On-site',
            type: 'Full-time',
            description: 'Join our team as a Senior HR Trainer and help organizations develop their human resources capabilities.',
            requirements: [
                '5+ years of HR training experience',
                'Certified HR professional (PHR, SPHR, or SHRM)',
                'Excellent presentation and communication skills',
                'Experience with adult learning principles'
            ]
        },
        {
            title: 'HR Training Coordinator',
            location: 'City, State',
            type: 'Full-time',
            description: 'Support our training operations and help coordinate programs for our diverse client base.',
            requirements: [
                '2+ years of training coordination experience',
                'Strong organizational and project management skills',
                'Proficiency in learning management systems',
                'Excellent customer service skills'
            ]
        },
        {
            title: 'HR Content Developer',
            location: 'Remote',
            type: 'Contract',
            description: 'Create engaging training materials and content for our HR development programs.',
            requirements: [
                '3+ years of instructional design experience',
                'Strong writing and content creation skills',
                'Knowledge of HR best practices',
                'Experience with e-learning platforms'
            ]
        }
    ];
    
    jobsGrid.innerHTML = jobs.map(job => `
        <div class="job-card">
            <div class="job-header">
                <div>
                    <h3 class="job-title">${job.title}</h3>
                    <p class="job-location">${job.location}</p>
                </div>
                <span class="job-type">${job.type}</span>
            </div>
            <p class="job-description">${job.description}</p>
            <ul class="job-requirements">
                ${job.requirements.map(req => `<li>${req}</li>`).join('')}
            </ul>
            <a href="#contact" class="btn btn-outline">Apply Now</a>
        </div>
    `).join('');
}

// ===== COUNTER ANIMATIONS =====
function initCounterAnimations() {
    const counters = document.querySelectorAll('.stat-item h3, .counter');
    
    const animateCounter = (counter) => {
        const target = parseInt(counter.getAttribute('data-target') || counter.textContent.replace(/[^\d]/g, ''));
        const duration = 2000; // 2 seconds
        const increment = target / (duration / 16); // 60fps
        let current = 0;
        
        const updateCounter = () => {
            current += increment;
            if (current < target) {
                counter.textContent = Math.floor(current);
                requestAnimationFrame(updateCounter);
            } else {
                counter.textContent = target;
                // Add a subtle bounce effect when counter completes
                counter.style.transform = 'scale(1.1)';
                setTimeout(() => {
                    counter.style.transform = 'scale(1)';
                }, 200);
            }
        };
        
        updateCounter();
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCounter(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });
    
    counters.forEach(counter => observer.observe(counter));
    
    // Special animation for hero stats - animate immediately when page loads
    const heroCounters = document.querySelectorAll('.hero .counter');
    heroCounters.forEach((counter, index) => {
        setTimeout(() => {
            animateCounter(counter);
        }, 1000 + (index * 300)); // Stagger the animations
    });
}

// ===== PROGRESS BAR ANIMATIONS =====
function initProgressAnimations() {
    const progressBars = document.querySelectorAll('.progress');
    
    const animateProgress = (progressBar) => {
        const width = progressBar.style.width;
        progressBar.style.width = '0%';
        
        setTimeout(() => {
            progressBar.style.width = width;
        }, 100);
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateProgress(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });
    
    progressBars.forEach(bar => observer.observe(bar));
}

// ===== LAZY LOADING =====
function initLazyLoading() {
    const images = document.querySelectorAll('img[data-src]');
    
    const imageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
                imageObserver.unobserve(img);
            }
        });
    });
    
    images.forEach(img => imageObserver.observe(img));
}

// ===== UTILITY FUNCTIONS =====
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// ===== PERFORMANCE OPTIMIZATIONS =====
function initPerformanceOptimizations() {
    // Throttle scroll events
    const throttledScrollHandler = debounce(() => {
        // Handle scroll-based animations
    }, 16);
    
    window.addEventListener('scroll', throttledScrollHandler);
    
    // Preload critical resources
    const criticalImages = [
        'assets/images/about-team.jpg',
        'assets/images/conference-room.jpg',
        'assets/images/simulation-center.jpg',
        'assets/images/breakout-area.jpeg'
    ];
    
    criticalImages.forEach(src => {
        const link = document.createElement('link');
        link.rel = 'preload';
        link.as = 'image';
        link.href = src;
        document.head.appendChild(link);
    });
}

// ===== ACCESSIBILITY ENHANCEMENTS =====
function initAccessibility() {
    // Enhance keyboard navigation
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            navMenu.classList.remove('active');
            navToggle.classList.remove('active');
        }
    });
}

// ===== INITIALIZATION =====
document.addEventListener('DOMContentLoaded', () => {
    
    initNavigation();
    
    initScrollAnimations();
    
    initFormHandling();
    
    initCounterAnimations();
    
    initProgressAnimations();
    
    initLazyLoading();
    
    initPerformanceOptimizations();
    
    initAccessibility();
    
    loadJobs();
    
    // Add loading animation to page
    document.body.classList.add('loaded');

    // Founder Phase 2 Interactivity
    initFounderInteractivity();

    // Founder Phase 4 dynamic content & UX
    initFounderContent();
    initFounderStoryVideo();
    initStoryProgress();
});

// ===== SERVICE WORKER FOR PWA FEATURES =====
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then(registration => {
                // Service Worker registered successfully
            })
            .catch(registrationError => {
                // Service Worker registration failed
            });
    });
}

// ===== ANALYTICS TRACKING =====
function trackEvent(category, action, label = null) {
    if (typeof gtag !== 'undefined') {
        gtag('event', action, {
            event_category: category,
            event_label: label
        });
    }
}

// Track form submissions
document.addEventListener('submit', (e) => {
    if (e.target.id === 'contact-form') {
        trackEvent('Form', 'Submit', 'Contact Form');
    }
});

// Track button clicks
document.addEventListener('click', (e) => {
    if (e.target.matches('.btn')) {
        const buttonText = e.target.textContent.trim();
        trackEvent('Button', 'Click', buttonText);
    }
});

// Track navigation clicks
document.addEventListener('click', (e) => {
    if (e.target.matches('.nav-link')) {
        const section = e.target.getAttribute('href').substring(1);
        trackEvent('Navigation', 'Click', section);
    }
});

// ===== ERROR HANDLING =====
window.addEventListener('error', (e) => {
    console.error('JavaScript error:', e.error);
    // In production, you might want to send this to an error tracking service
});

// ===== RESIZE HANDLER =====
window.addEventListener('resize', debounce(() => {
    // Handle responsive adjustments
    if (window.innerWidth > 768) {
        navMenu.classList.remove('active');
        navToggle.classList.remove('active');
    }
}, 250));

// ===== FOUNDER COUNTER ANIMATION =====
function initFounderCounters() {
    const counters = document.querySelectorAll('.stat-number[data-target]');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const counter = entry.target;
                const target = counter.getAttribute('data-target');
                const suffix = counter.getAttribute('data-suffix') || '';
                
                // Check if target is a number (for animation) or string (for direct display)
                if (isNaN(target)) {
                    // For non-numeric strings, display directly
                    counter.textContent = target;
                } else {
                    // For numeric values, animate the counter and add suffix
                    animateFounderCounter(counter, parseInt(target), suffix);
                }
                observer.unobserve(counter);
            }
        });
    }, { threshold: 0.5 });
    
    counters.forEach(counter => {
        observer.observe(counter);
    });
}

function animateFounderCounter(element, target, suffix = '', duration = 2000) {
    let start = 0;
    const increment = target / (duration / 16);
    
    function updateCounter() {
        start += increment;
        if (start < target) {
            element.textContent = Math.floor(start) + suffix;
            requestAnimationFrame(updateCounter);
        } else {
            element.textContent = target + suffix;
        }
    }
    
    updateCounter();
}

// Initialize founder counters
document.addEventListener('DOMContentLoaded', function() {
    initFounderCounters();
});

// Poster Modal — Google Drive-style image viewer
class PosterModal {
    constructor() {
        this.modal = document.getElementById('posterModal');
        if (!this.modal) return;

        this.modalImage = document.getElementById('posterModalImage');
        this.modalTitle = document.getElementById('posterModalTitle');
        this.modalClose = document.getElementById('posterModalClose');
        this.zoomInBtn = document.getElementById('zoomIn');
        this.zoomOutBtn = document.getElementById('zoomOut');
        this.zoomResetBtn = document.getElementById('zoomReset');
        this.zoomActualBtn = document.getElementById('zoomActual');
        this.zoomLevel = document.getElementById('zoomLevel');
        this.viewport = document.getElementById('posterViewport') || document.querySelector('.poster-zoom-container');
        this.canvas = document.getElementById('posterCanvas');
        this.thumbs = document.getElementById('posterThumbs');

        this.scale = 1;
        this.minScale = 0.2;
        this.maxScale = 6;
        this.translateX = 0;
        this.translateY = 0;
        this.fitScale = 1;

        this.isDragging = false;
        this.dragMoved = false;
        this.lastPointerX = 0;
        this.lastPointerY = 0;
        this.pointers = new Map();
        this.lastPinchDist = 0;

        this.images = [];
        this.activeIndex = 0;

        this._boundKeydown = (e) => this.onKeydown(e);
        this.init();
        window.posterModalInstance = this;
    }

    init() {
        this.addPosterClickHandlers();

        this.modalClose?.addEventListener('click', () => this.close());
        this.zoomInBtn?.addEventListener('click', () => this.zoomBy(1.25));
        this.zoomOutBtn?.addEventListener('click', () => this.zoomBy(1 / 1.25));
        this.zoomResetBtn?.addEventListener('click', () => this.fitToScreen());
        this.zoomActualBtn?.addEventListener('click', () => this.actualSize());

        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal || e.target.classList.contains('poster-modal-overlay')) {
                this.close();
            }
        });

        // Wheel zoom toward cursor (Google Drive style)
        this.viewport.addEventListener('wheel', (e) => {
            if (!this.modal.classList.contains('show')) return;
            e.preventDefault();
            const factor = e.deltaY < 0 ? 1.12 : 1 / 1.12;
            const rect = this.viewport.getBoundingClientRect();
            this.zoomAt(this.scale * factor, e.clientX - rect.left, e.clientY - rect.top);
        }, { passive: false });

        // Pointer pan + pinch
        this.viewport.addEventListener('pointerdown', (e) => this.onPointerDown(e));
        this.viewport.addEventListener('pointermove', (e) => this.onPointerMove(e));
        this.viewport.addEventListener('pointerup', (e) => this.onPointerUp(e));
        this.viewport.addEventListener('pointercancel', (e) => this.onPointerUp(e));
        this.viewport.addEventListener('pointerleave', (e) => {
            if (this.pointers.has(e.pointerId)) this.onPointerUp(e);
        });

        // Double-click / double-tap toggle fit <-> ~2x
        this.viewport.addEventListener('dblclick', (e) => {
            e.preventDefault();
            const rect = this.viewport.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            if (Math.abs(this.scale - this.fitScale) < 0.05) {
                this.zoomAt(Math.min(this.fitScale * 2, this.maxScale), x, y);
            } else {
                this.fitToScreen();
            }
        });

        window.addEventListener('resize', () => {
            if (this.modal.classList.contains('show')) {
                this.fitToScreen();
            }
        });
    }

    addPosterClickHandlers() {
        document.querySelectorAll('.programme-poster-img').forEach((img) => {
            img.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const primary = img.dataset.posterSrc || img.getAttribute('src');
                const secondary = img.dataset.posterSecondary || '';
                const title = img.dataset.posterTitle || (img.alt || 'Programme Poster').replace(/ Poster$/i, '');
                this.open(primary, title, secondary);
            });
        });
    }

    open(imageSrc, title, secondarySrc = '') {
        if (!this.modal || !this.modalImage) return;

        this.images = [imageSrc].filter(Boolean);
        if (secondarySrc) this.images.push(secondarySrc);
        this.activeIndex = 0;

        this.modalTitle.textContent = title || 'Programme Poster';
        this.renderThumbs();
        this.loadActiveImage(() => {
            this.modal.classList.add('show');
            this.modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            document.addEventListener('keydown', this._boundKeydown);
            this.fitToScreen();
        });
    }

    loadActiveImage(onReady) {
        const src = this.images[this.activeIndex];
        if (!src) return;

        // Always update the persistent img node (never destroy it)
        const finish = () => {
            this.modalImage.alt = this.modalTitle.textContent || 'Programme Poster';
            if (typeof onReady === 'function') onReady();
            else this.fitToScreen();
        };

        if (this.modalImage.getAttribute('src') === src && this.modalImage.complete && this.modalImage.naturalWidth) {
            finish();
            return;
        }

        this.modalImage.onload = () => {
            this.modalImage.onload = null;
            this.modalImage.onerror = null;
            finish();
        };
        this.modalImage.onerror = () => {
            this.modalImage.onload = null;
            this.modalImage.onerror = null;
            finish();
        };
        // Bust any stale painted frame when switching posters
        this.modalImage.removeAttribute('src');
        this.modalImage.src = src;
    }

    renderThumbs() {
        if (!this.thumbs) return;
        this.thumbs.innerHTML = '';
        if (this.images.length < 2) {
            this.thumbs.hidden = true;
            return;
        }
        this.thumbs.hidden = false;
        this.images.forEach((src, index) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'poster-thumb' + (index === this.activeIndex ? ' active' : '');
            btn.innerHTML = `<img src="${src}" alt="Poster ${index + 1}" draggable="false">`;
            btn.addEventListener('click', () => {
                if (this.activeIndex === index) return;
                this.activeIndex = index;
                this.renderThumbs();
                this.loadActiveImage();
            });
            this.thumbs.appendChild(btn);
        });
    }

    close() {
        if (!this.modal) return;
        this.modal.classList.remove('show');
        this.modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        document.removeEventListener('keydown', this._boundKeydown);
        this.isDragging = false;
        this.pointers.clear();
    }

    onKeydown(e) {
        if (!this.modal.classList.contains('show')) return;
        switch (e.key) {
            case 'Escape':
                this.close();
                break;
            case '+':
            case '=':
                e.preventDefault();
                this.zoomBy(1.25);
                break;
            case '-':
            case '_':
                e.preventDefault();
                this.zoomBy(1 / 1.25);
                break;
            case '0':
                e.preventDefault();
                this.fitToScreen();
                break;
            case '1':
                e.preventDefault();
                this.actualSize();
                break;
        }
    }

    fitToScreen() {
        if (!this.modalImage.naturalWidth || !this.viewport) return;
        const vw = this.viewport.clientWidth;
        const vh = this.viewport.clientHeight;
        const iw = this.modalImage.naturalWidth;
        const ih = this.modalImage.naturalHeight;
        const pad = 24;
        this.fitScale = Math.min((vw - pad) / iw, (vh - pad) / ih, 1);
        this.scale = this.fitScale;
        this.translateX = (vw - iw * this.scale) / 2;
        this.translateY = (vh - ih * this.scale) / 2;
        this.applyTransform(false);
    }

    actualSize() {
        if (!this.modalImage.naturalWidth || !this.viewport) return;
        const vw = this.viewport.clientWidth;
        const vh = this.viewport.clientHeight;
        const iw = this.modalImage.naturalWidth;
        const ih = this.modalImage.naturalHeight;
        this.scale = 1;
        this.translateX = (vw - iw) / 2;
        this.translateY = (vh - ih) / 2;
        this.applyTransform();
    }

    zoomBy(factor) {
        if (!this.viewport) return;
        const rect = this.viewport.getBoundingClientRect();
        this.zoomAt(this.scale * factor, rect.width / 2, rect.height / 2);
    }

    zoomAt(nextScale, originX, originY) {
        const newScale = Math.min(this.maxScale, Math.max(this.minScale, nextScale));
        const contentX = (originX - this.translateX) / this.scale;
        const contentY = (originY - this.translateY) / this.scale;
        this.scale = newScale;
        this.translateX = originX - contentX * this.scale;
        this.translateY = originY - contentY * this.scale;
        this.applyTransform();
    }

    applyTransform(animate = true) {
        if (!this.canvas && !this.modalImage) return;
        const target = this.canvas || this.modalImage;
        if (animate) target.style.transition = 'transform 0.12s ease-out';
        else target.style.transition = 'none';
        target.style.transform = `translate(${this.translateX}px, ${this.translateY}px) scale(${this.scale})`;
        if (this.zoomLevel) {
            this.zoomLevel.textContent = `${Math.round(this.scale * 100)}%`;
        }
        if (this.viewport) {
            this.viewport.classList.toggle('is-zoomed', this.scale > this.fitScale + 0.02);
        }
    }

    onPointerDown(e) {
        if (!this.modal.classList.contains('show')) return;
        this.viewport.setPointerCapture?.(e.pointerId);
        this.pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
        this.dragMoved = false;

        if (this.pointers.size === 1) {
            this.isDragging = true;
            this.lastPointerX = e.clientX;
            this.lastPointerY = e.clientY;
            this.viewport.classList.add('is-panning');
        } else if (this.pointers.size === 2) {
            this.isDragging = false;
            const pts = [...this.pointers.values()];
            this.lastPinchDist = Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y);
        }
        e.preventDefault();
    }

    onPointerMove(e) {
        if (!this.pointers.has(e.pointerId)) return;
        this.pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });

        if (this.pointers.size === 2) {
            const pts = [...this.pointers.values()];
            const dist = Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y);
            if (this.lastPinchDist > 0) {
                const rect = this.viewport.getBoundingClientRect();
                const midX = (pts[0].x + pts[1].x) / 2 - rect.left;
                const midY = (pts[0].y + pts[1].y) / 2 - rect.top;
                this.zoomAt(this.scale * (dist / this.lastPinchDist), midX, midY);
            }
            this.lastPinchDist = dist;
            e.preventDefault();
            return;
        }

        if (!this.isDragging || this.pointers.size !== 1) return;
        const dx = e.clientX - this.lastPointerX;
        const dy = e.clientY - this.lastPointerY;
        if (Math.abs(dx) > 2 || Math.abs(dy) > 2) this.dragMoved = true;
        this.lastPointerX = e.clientX;
        this.lastPointerY = e.clientY;
        this.translateX += dx;
        this.translateY += dy;
        this.applyTransform(false);
        e.preventDefault();
    }

    onPointerUp(e) {
        this.pointers.delete(e.pointerId);
        if (this.pointers.size < 2) this.lastPinchDist = 0;
        if (this.pointers.size === 0) {
            this.isDragging = false;
            this.viewport.classList.remove('is-panning');
        } else if (this.pointers.size === 1) {
            const pt = [...this.pointers.values()][0];
            this.isDragging = true;
            this.lastPointerX = pt.x;
            this.lastPointerY = pt.y;
        }
    }
}

// Backward-compatible entry point used by older markup
function openPosterModal(element, primaryImage, secondaryImage = '') {
    if (!window.posterModalInstance) {
        window.posterModalInstance = new PosterModal();
    }
    const title = (element && (element.dataset?.posterTitle || element.alt)) || 'Programme Poster';
    window.posterModalInstance.open(
        primaryImage || element?.dataset?.posterSrc || element?.src,
        String(title).replace(/ Poster$/i, ''),
        secondaryImage || element?.dataset?.posterSecondary || ''
    );
}

function createPosterModal() {
    // Modal markup lives in programmes.php; keep stub for compatibility
    return document.getElementById('posterModal');
}

function initPosterZoom() {
    // Deprecated — handled by PosterModal
}

// ===== TRAINERS CAROUSEL FUNCTIONALITY =====
function initTrainersCarousel() {
    const carousel = document.getElementById('trainersCarousel');
    const track = document.getElementById('trainersTrack');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const dots = document.querySelectorAll('.dot');
    
    if (!carousel || !track || !prevBtn || !nextBtn) return;
    
    let currentSlide = 0;
    const totalSlides = document.querySelectorAll('.trainer-card').length;
    let isTransitioning = false;
    
    // Touch/Mouse swipe variables
    let startX = 0;
    let startY = 0;
    let currentX = 0;
    let currentY = 0;
    let isDragging = false;
    let startTime = 0;
    
    function updateCarousel() {
        if (isTransitioning) return;
        
        isTransitioning = true;
        
        // Update track position
        track.style.transform = `translateX(-${currentSlide * 100}%)`;
        
        // Update active states
        document.querySelectorAll('.trainer-card').forEach((card, index) => {
            card.classList.toggle('active', index === currentSlide);
        });
        
        // Update dots
        dots.forEach((dot, index) => {
            dot.classList.toggle('active', index === currentSlide);
        });
        
        // Update button states
        prevBtn.disabled = currentSlide === 0;
        nextBtn.disabled = currentSlide === totalSlides - 1;
        
        setTimeout(() => {
            isTransitioning = false;
        }, 500);
    }
    
    function nextSlide() {
        if (currentSlide < totalSlides - 1) {
            currentSlide++;
            updateCarousel();
        }
    }
    
    function prevSlide() {
        if (currentSlide > 0) {
            currentSlide--;
            updateCarousel();
        }
    }
    
    function goToSlide(slideIndex) {
        if (slideIndex >= 0 && slideIndex < totalSlides) {
            currentSlide = slideIndex;
            updateCarousel();
        }
    }
    
    // Event listeners
    nextBtn.addEventListener('click', nextSlide);
    prevBtn.addEventListener('click', prevSlide);
    
    // Dot navigation
    dots.forEach((dot, index) => {
        dot.addEventListener('click', () => goToSlide(index));
    });
    
    // Touch/Mouse swipe functionality
    function handleStart(e) {
        if (isTransitioning) return;
        
        isDragging = true;
        startTime = Date.now();
        
        if (e.type === 'touchstart') {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
        } else {
            startX = e.clientX;
            startY = e.clientY;
        }
        
        track.style.transition = 'none';
    }
    
    function handleMove(e) {
        if (!isDragging || isTransitioning) return;
        
        e.preventDefault();
        
        if (e.type === 'touchmove') {
            currentX = e.touches[0].clientX;
            currentY = e.touches[0].clientY;
        } else {
            currentX = e.clientX;
            currentY = e.clientY;
        }
        
        const deltaX = currentX - startX;
        const deltaY = currentY - startY;
        
        // Only handle horizontal swipes
        if (Math.abs(deltaX) > Math.abs(deltaY)) {
            const translateX = -currentSlide * 100 + (deltaX / carousel.offsetWidth) * 100;
            track.style.transform = `translateX(${translateX}%)`;
        }
    }
    
    function handleEnd(e) {
        if (!isDragging || isTransitioning) return;
        
        isDragging = false;
        track.style.transition = 'transform 0.5s cubic-bezier(0.4, 0, 0.2, 1)';
        
        const deltaX = currentX - startX;
        const deltaY = currentY - startY;
        const deltaTime = Date.now() - startTime;
        
        // Only handle horizontal swipes
        if (Math.abs(deltaX) > Math.abs(deltaY)) {
            const threshold = carousel.offsetWidth * 0.2; // 20% threshold
            const velocity = Math.abs(deltaX) / deltaTime;
            
            if (Math.abs(deltaX) > threshold || velocity > 0.5) {
                if (deltaX > 0 && currentSlide > 0) {
                    prevSlide();
                } else if (deltaX < 0 && currentSlide < totalSlides - 1) {
                    nextSlide();
                } else {
                    updateCarousel(); // Snap back
                }
            } else {
                updateCarousel(); // Snap back
            }
        } else {
            updateCarousel(); // Snap back
        }
    }
    
    // Touch events
    track.addEventListener('touchstart', handleStart, { passive: false });
    track.addEventListener('touchmove', handleMove, { passive: false });
    track.addEventListener('touchend', handleEnd);
    
    // Mouse events
    track.addEventListener('mousedown', handleStart);
    track.addEventListener('mousemove', handleMove);
    track.addEventListener('mouseup', handleEnd);
    track.addEventListener('mouseleave', handleEnd);
    
    // Keyboard navigation
    document.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft') {
            e.preventDefault();
            prevSlide();
        } else if (e.key === 'ArrowRight') {
            e.preventDefault();
            nextSlide();
        }
    });
    
    // Auto-play (optional)
    let autoPlayInterval;
    
    function startAutoPlay() {
        autoPlayInterval = setInterval(() => {
            if (currentSlide < totalSlides - 1) {
                nextSlide();
            } else {
                goToSlide(0);
            }
        }, 5000);
    }
    
    function stopAutoPlay() {
        clearInterval(autoPlayInterval);
    }
    
    // Start auto-play
    startAutoPlay();
    
    // Pause auto-play on hover
    carousel.addEventListener('mouseenter', stopAutoPlay);
    carousel.addEventListener('mouseleave', startAutoPlay);
    
    // Pause auto-play on touch
    track.addEventListener('touchstart', stopAutoPlay);
    track.addEventListener('touchend', () => {
        setTimeout(startAutoPlay, 2000);
    });
    
    // Initialize
    updateCarousel();
}


// Initialize all animations when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initFounderStoryVideo();
    loadFeaturedProgrammes();
    
    // Initialize poster modal if on programmes page
    if (document.getElementById('posterModal')) {
        new PosterModal();
    }
    
    // Initialize trainers carousel if on programmes page
    if (document.getElementById('trainersCarousel')) {
        initTrainersCarousel();
    }
}); // Load Featured Programmes as Slideshow
async function loadFeaturedProgrammes() {
    const container = document.getElementById('featured-programmes-container');
    const dotsContainer = document.getElementById('slideshow-dots');
    if (!container) return;

    try {
        const response = await fetch('api/featured-programmes.php');
        const result = await response.json();

        if (result.success && result.data && result.data.length > 0) {
            const programmes = result.data;
            let slidesHtml = '';

            programmes.forEach((prog, i) => {
                const title = prog.title || 'Training Programme';
                const bannerSrc = prog.banner_image || '';

                if (!bannerSrc) return; // Skip programmes without banner

                const canRegister = prog.registration_status === 'open';
                const slideHref = canRegister
                    ? `programme-registration.php?programme_id=${encodeURIComponent(prog.id)}`
                    : 'programmes.php';
                const slideLabel = (canRegister ? 'Register for ' : '') + title.replace(/"/g, '&quot;');

                slidesHtml += `
                    <a href="${slideHref}" 
                       class="slideshow-slide${i === 0 ? ' active' : ''}" 
                       data-index="${i}" 
                       draggable="false"
                       title="${title.replace(/"/g, '&quot;')}"
                       aria-label="${slideLabel}">
                        <div class="slide-ambient" style="background-image: url('${bannerSrc.replace(/'/g, '%27')}');" aria-hidden="true"></div>
                        <div class="slide-frame">
                            <img src="${bannerSrc}" 
                                 alt="${title.replace(/"/g, '&quot;')}" 
                                 draggable="false"
                                 loading="${i === 0 ? 'eager' : 'lazy'}" 
                                 class="slide-banner-img"
                                 onerror="this.closest('.slideshow-slide').style.display='none';">
                        </div>
                    </a>
                `;
            });

            container.innerHTML = slidesHtml;

            // Recount visible slides (some may be hidden via onerror)
            const allSlides = container.querySelectorAll('.slideshow-slide');
            const wash = document.getElementById('slideshow-wash');
            const hint = document.getElementById('slideshow-hint');
            let washTimer = null;

            function paintWash(slide) {
                if (!wash || !slide) return;
                const ambient = slide.querySelector('.slide-ambient');
                const image = ambient ? ambient.style.backgroundImage : '';
                if (!image || wash.style.backgroundImage === image) return;
                if (!wash.style.backgroundImage) {
                    wash.style.backgroundImage = image;
                    return;
                }
                if (washTimer) clearTimeout(washTimer);
                wash.classList.add('is-fading');
                washTimer = setTimeout(() => {
                    wash.style.backgroundImage = image;
                    wash.classList.remove('is-fading');
                    washTimer = null;
                }, 390);
            }

            if (allSlides.length) paintWash(allSlides[0]);
            if (hint) hint.hidden = allSlides.length < 2;

            // Build dots
            if (dotsContainer) {
                if (allSlides.length > 1) {
                    let dotsHtml = '';
                    allSlides.forEach((_, i) => {
                        dotsHtml += `<button class="slideshow-dot${i === 0 ? ' active' : ''}" data-index="${i}" aria-label="Slide ${i + 1}"></button>`;
                    });
                    dotsContainer.innerHTML = dotsHtml;
                } else {
                    dotsContainer.innerHTML = '';
                }
            }

            // Controls, swipe & autoplay
            if (allSlides.length > 1) {
                let current = 0;
                const slides = allSlides;
                const dots = dotsContainer ? dotsContainer.querySelectorAll('.slideshow-dot') : [];
                const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                let autoTimer = null;
                let dragging = false;
                let pointerId = null;
                let startX = 0;
                let startY = 0;
                let dx = 0;
                let axis = null;
                let moved = false;
                let startTime = 0;
                let settleTimer = null;
                let pendingIndex = null;

                container.classList.add('is-swipeable');

                function wrapIndex(index) {
                    return (index + slides.length) % slides.length;
                }

                function setActiveDot(index) {
                    const active = wrapIndex(index);
                    dots.forEach((dot, i) => {
                        dot.classList.toggle('active', i === active);
                    });
                }

                function clearDragStyles() {
                    slides.forEach(slide => {
                        slide.style.transition = 'none';
                        slide.style.transform = '';
                        slide.style.opacity = '';
                        slide.style.visibility = '';
                        slide.style.zIndex = '';
                    });
                    void container.offsetWidth;
                    slides.forEach(slide => {
                        slide.style.transition = '';
                    });
                }

                function goToSlide(index) {
                    cancelSettle();
                    slides[current].classList.remove('active');
                    if (dots[current]) dots[current].classList.remove('active');
                    current = wrapIndex(index);
                    slides[current].classList.add('active');
                    if (dots[current]) dots[current].classList.add('active');
                    paintWash(slides[current]);
                    clearDragStyles();
                }

                function animateTo(index) {
                    if (dragging) return;
                    if (settleTimer) {
                        clearTimeout(settleTimer);
                        settleTimer = null;
                        if (pendingIndex !== null) {
                            const commit = pendingIndex;
                            pendingIndex = null;
                            goToSlide(commit);
                        }
                    }

                    const next = wrapIndex(index);
                    if (next === current) return;
                    setActiveDot(next);
                    if (reduceMotion) {
                        goToSlide(next);
                        return;
                    }

                    const dir = index >= current ? 1 : -1;
                    const width = container.offsetWidth || 1;
                    const active = slides[current];
                    const incoming = slides[next];
                    paintWash(incoming);
                    const ms = 780;
                    const ease = 'transform ' + ms + 'ms var(--ease-out)';

                    slides.forEach(slide => {
                        if (slide === active || slide === incoming) return;
                        slide.style.transition = 'none';
                        slide.style.opacity = '0';
                        slide.style.visibility = 'hidden';
                        slide.style.transform = '';
                        slide.style.zIndex = '0';
                    });

                    incoming.style.transition = 'none';
                    incoming.style.visibility = 'visible';
                    incoming.style.opacity = '1';
                    incoming.style.zIndex = '1';
                    incoming.style.transform = 'translateX(' + (dir > 0 ? width : -width) + 'px)';

                    active.style.transition = 'none';
                    active.style.visibility = 'visible';
                    active.style.opacity = '1';
                    active.style.zIndex = '2';
                    active.style.transform = 'translateX(0px)';

                    void container.offsetWidth;

                    active.style.transition = ease;
                    active.style.transform = 'translateX(' + (dir > 0 ? -width : width) + 'px)';
                    incoming.style.transition = ease;
                    incoming.style.transform = 'translateX(0px)';

                    pendingIndex = next;
                    container.classList.add('is-settling');
                    settleTimer = setTimeout(() => {
                        settleTimer = null;
                        container.classList.remove('is-settling');
                        const commit = pendingIndex;
                        pendingIndex = null;
                        if (commit !== null) goToSlide(commit);
                    }, ms);
                }

                function cancelSettle() {
                    if (settleTimer) {
                        clearTimeout(settleTimer);
                        settleTimer = null;
                    }
                    pendingIndex = null;
                    container.classList.remove('is-settling');
                }

                function applyDrag(delta) {
                    const width = container.offsetWidth || 1;
                    const dir = delta < 0 ? 1 : -1;
                    const active = slides[current];
                    const incoming = slides[wrapIndex(current + dir)];

                    slides.forEach(slide => {
                        if (slide === active || slide === incoming) return;
                        slide.style.transition = 'none';
                        slide.style.opacity = '0';
                        slide.style.visibility = 'hidden';
                        slide.style.transform = '';
                        slide.style.zIndex = '0';
                    });

                    active.style.transition = 'none';
                    active.style.visibility = 'visible';
                    active.style.opacity = '1';
                    active.style.zIndex = '2';
                    active.style.transform = 'translateX(' + delta + 'px)';

                    if (incoming !== active) {
                        const incomingOffset = delta < 0 ? width + delta : -width + delta;
                        incoming.style.transition = 'none';
                        incoming.style.visibility = 'visible';
                        incoming.style.opacity = '1';
                        incoming.style.zIndex = '1';
                        incoming.style.transform = 'translateX(' + incomingOffset + 'px)';
                    }

                    const shown = Math.abs(delta) > width / 2 ? wrapIndex(current + dir) : current;
                    setActiveDot(shown);
                }

                function finishDrag(commit, done) {
                    const width = container.offsetWidth || 1;
                    const delta = dx;
                    const dir = delta < 0 ? 1 : -1;
                    const active = slides[current];
                    const incoming = slides[wrapIndex(current + dir)];
                    const snapMs = reduceMotion ? 0 : 420;
                    const ease = 'transform ' + snapMs + 'ms var(--ease-out)';

                    if (Math.abs(delta) < 1) {
                        clearDragStyles();
                        if (done) done();
                        return;
                    }

                    if (!commit) {
                        active.style.transition = ease;
                        active.style.transform = 'translateX(0)';
                        if (incoming !== active) {
                            const parked = delta < 0 ? width : -width;
                            incoming.style.transition = ease;
                            incoming.style.transform = 'translateX(' + parked + 'px)';
                        }
                        pendingIndex = null;
                        setActiveDot(current);
                    } else {
                        active.style.transition = ease;
                        active.style.transform = 'translateX(' + (delta < 0 ? -width : width) + 'px)';
                        if (incoming !== active) {
                            incoming.style.transition = ease;
                            incoming.style.transform = 'translateX(0)';
                        }
                        pendingIndex = wrapIndex(current + dir);
                        setActiveDot(pendingIndex);
                    }

                    settleTimer = setTimeout(() => {
                        settleTimer = null;
                        if (pendingIndex !== null) {
                            const next = pendingIndex;
                            pendingIndex = null;
                            goToSlide(next);
                        } else {
                            clearDragStyles();
                        }
                        if (done) done();
                    }, snapMs);
                }

                container.addEventListener('pointerdown', (e) => {
                    if (e.pointerType === 'mouse' && e.button !== 0) return;
                    if (settleTimer) {
                        clearTimeout(settleTimer);
                        settleTimer = null;
                        if (pendingIndex !== null) {
                            goToSlide(pendingIndex);
                        } else {
                            clearDragStyles();
                        }
                    }
                    dragging = true;
                    moved = false;
                    axis = null;
                    dx = 0;
                    startX = e.clientX;
                    startY = e.clientY;
                    startTime = Date.now();
                    pointerId = e.pointerId;
                    if (e.pointerType === 'mouse') {
                        container.setPointerCapture(e.pointerId);
                    }
                    container.classList.add('is-dragging');
                    clearInterval(autoTimer);
                });

                container.addEventListener('pointermove', (e) => {
                    if (!dragging || e.pointerId !== pointerId) return;
                    const moveX = e.clientX - startX;
                    const moveY = e.clientY - startY;
                    if (!axis) {
                        if (Math.abs(moveX) < 8 && Math.abs(moveY) < 8) return;
                        axis = Math.abs(moveX) > Math.abs(moveY) ? 'x' : 'y';
                        if (axis === 'y') {
                            dragging = false;
                            container.classList.remove('is-dragging');
                            if (container.hasPointerCapture(e.pointerId)) {
                                container.releasePointerCapture(e.pointerId);
                            }
                            resetTimer();
                            return;
                        }
                        if (e.pointerType !== 'mouse') {
                            container.setPointerCapture(e.pointerId);
                        }
                    }
                    if (axis !== 'x') return;
                    if (Math.abs(moveX) > 8) moved = true;
                    dx = moveX;
                    applyDrag(dx);
                });

                function endDrag(e) {
                    if (!dragging || e.pointerId !== pointerId) return;
                    dragging = false;
                    container.classList.remove('is-dragging');
                    if (container.hasPointerCapture(e.pointerId)) {
                        container.releasePointerCapture(e.pointerId);
                    }
                    if (axis !== 'x') {
                        clearDragStyles();
                        resetTimer();
                        axis = null;
                        dx = 0;
                        return;
                    }
                    const width = container.offsetWidth || 1;
                    const elapsed = Math.max(Date.now() - startTime, 1);
                    const velocity = Math.abs(dx) / elapsed;
                    const threshold = Math.max(48, width * 0.15);
                    const commit = Math.abs(dx) > threshold || (Math.abs(dx) > 24 && velocity > 0.5);
                    finishDrag(commit, resetTimer);
                    axis = null;
                    dx = 0;
                }

                container.addEventListener('pointerup', endDrag);
                container.addEventListener('pointercancel', (e) => {
                    if (!dragging || e.pointerId !== pointerId) return;
                    dragging = false;
                    container.classList.remove('is-dragging');
                    axis = null;
                    finishDrag(false, resetTimer);
                    dx = 0;
                });

                container.addEventListener('click', (e) => {
                    if (!moved) return;
                    e.preventDefault();
                    e.stopPropagation();
                    moved = false;
                }, true);

                dots.forEach(dot => {
                    dot.addEventListener('click', (e) => {
                        e.preventDefault();
                        animateTo(parseInt(dot.dataset.index, 10));
                        resetTimer();
                    });
                });

                function resetTimer() {
                    clearInterval(autoTimer);
                    autoTimer = setInterval(() => {
                        if (dragging || settleTimer) return;
                        animateTo(current + 1);
                    }, 5000);
                }

                resetTimer();
            }
        } else {
            // No featured programmes — hide the entire section
            const section = document.getElementById('featured-slideshow');
            if (section) section.style.display = 'none';
        }
    } catch (error) {
        console.error('Error loading featured programmes:', error);
        const section = document.getElementById('featured-slideshow');
        if (section) section.style.display = 'none';
    }
}

