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

    // Add hover effects for navigation links
    navLinks.forEach(link => {
        link.addEventListener('mouseenter', () => {
            link.style.transform = 'translateY(-2px) scale(1.05)';
        });
        
        link.addEventListener('mouseleave', () => {
            link.style.transform = 'translateY(0) scale(1)';
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

    // Subtle 3D tilt for cards
    const tiltTargets = section.querySelectorAll('.profile-card, .achievement-card, .expertise-card, .story-container');
    tiltTargets.forEach((card) => {
        card.addEventListener('mousemove', (e) => {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const rotateY = ((x / rect.width) - 0.5) * 10; // -5 to 5 deg
            const rotateX = ((y / rect.height) - 0.5) * -10; // -5 to 5 deg
            card.style.transform = `perspective(800px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
        });
        card.addEventListener('mouseleave', () => {
            card.style.transform = 'perspective(800px) rotateX(0deg) rotateY(0deg)';
        });
    });
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
            'She maintains strong rapport with local authorities such as KWSP, PERKESO, LHDN, JTK and others allowing her to handle HR matters efficiently and in line with client expectations.',
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
function initFormHandling() {
    if (contactForm) {
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

function validateField(e) {
    const field = e.target;
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
            // Remove common formatting characters and validate
            const cleanedPhone = value.replace(/[\s\-\(\)\.]/g, '');
            const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
            if (value && !phoneRegex.test(cleanedPhone)) {
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
        color: #ef4444;
        font-size: 0.875rem;
        margin-top: 0.25rem;
        animation: slideIn 0.3s ease;
    `;
    
    field.parentNode.appendChild(errorDiv);
    field.style.borderColor = '#ef4444';
}

function clearFieldError(e) {
    const field = e.target;
    const errorDiv = field.parentNode.querySelector('.field-error');
    if (errorDiv) {
        errorDiv.remove();
    }
    field.style.borderColor = '';
}

async function handleFormSubmit(e) {
    e.preventDefault();
    
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
        if (input.parentNode.querySelector('.field-error')) {
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
    let bgColor = '#3b82f6'; // default blue
    if (type === 'success') bgColor = '#10b981'; // green
    if (type === 'error') bgColor = '#ef4444'; // red
    
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
        'assets/images/breakout-area.jpg'
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

// ===== ADMIN ACCESS SHORTCUT =====
function initAdminShortcut() {
    let keySequence = [];
    let lastKeyTime = 0;
    const sequenceTimeout = 3000; // 3 seconds to complete sequence
    
    document.addEventListener('keydown', (e) => {
        const currentTime = Date.now();
        
        // Reset sequence if too much time has passed
        if (currentTime - lastKeyTime > sequenceTimeout) {
            keySequence = [];
        }
        
        // Check if it's the X key with Ctrl+Alt+Shift modifiers
        if (e.code === 'KeyX' && e.ctrlKey && e.altKey && e.shiftKey) {
            // Show admin access prompt
            showAdminPrompt();
            keySequence = []; // Reset sequence
            return;
        }
        
        // Add current key to sequence
        keySequence.push(e.code);
        lastKeyTime = currentTime;
        
        // Keep only the last 4 keys
        if (keySequence.length > 4) {
            keySequence.shift();
        }
    });
}

function showAdminPrompt() {
    // Create a modal for admin access
    const modal = document.createElement('div');
    modal.className = 'admin-modal';
    modal.innerHTML = `
        <div class="admin-modal-content">
            <div class="admin-modal-header">
                <h3>🔐 Admin Access</h3>
            </div>
            <div class="admin-modal-body">
                <p>Enter admin credentials to access the admin panel:</p>
                <form id="adminQuickForm">
                    <div class="form-group">
                        <label for="adminUsername">Username:</label>
                        <input type="text" id="adminUsername" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="adminPassword">Password:</label>
                        <input type="password" id="adminPassword" name="password" required>
                    </div>
                </form>
            </div>
            <div class="admin-modal-actions">
                <button type="submit" form="adminQuickForm" class="btn btn-primary">Access Admin Panel</button>
                <button type="button" class="btn btn-outline admin-modal-cancel">Cancel</button>
            </div>
        </div>
    `;
    
    // Add styles
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        backdrop-filter: blur(10px);
    `;
    
    const modalContent = modal.querySelector('.admin-modal-content');
    modalContent.style.cssText = `
        background: white;
        padding: 2rem;
        border-radius: 1rem;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        max-width: 400px;
        width: 90%;
        animation: modalSlideIn 0.3s ease;
        display: flex;
        flex-direction: column;
    `;
    
    // Style the modal body to take up available space
    const modalBody = modal.querySelector('.admin-modal-body');
    modalBody.style.cssText = `
        flex: 1;
        margin-bottom: 1.5rem;
    `;
    
    // Style the actions container to push buttons lower
    const modalActions = modal.querySelector('.admin-modal-actions');
    modalActions.style.cssText = `
        margin-top: auto;
        padding-top: 1.5rem;
        border-top: 1px solid #e5e7eb;
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
    `;
    
    document.body.appendChild(modal);
    
    // Handle form submission
    const form = modal.querySelector('#adminQuickForm');
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const username = document.getElementById('adminUsername').value;
        const password = document.getElementById('adminPassword').value;
        
        // Simple validation (in production, this should be server-side)
        if (username === 'admin' && password === 'xpresspoint2024') {
            window.location.href = './admin/index.php';
        } else {
            showNotification('Invalid credentials', 'error');
        }
    });
    
    // Handle cancel button
    const cancelBtn = modal.querySelector('.admin-modal-cancel');
    cancelBtn.addEventListener('click', () => {
        modal.remove();
    });
    
    // Close on outside click
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.remove();
        }
    });
    
    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            modal.remove();
        }
    });
    
    // Focus on username field
    setTimeout(() => {
        document.getElementById('adminUsername').focus();
    }, 100);
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
    
    initAdminShortcut();
    
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

// ===== ADVANCED CONTACT ANIMATIONS =====
function initContactAnimations() {
    const contactSection = document.getElementById('contact');
    if (!contactSection) return;

    const mouseParticles = document.getElementById('mouseParticles');
    const interactiveGlow = document.getElementById('interactiveGlow');
    const particles = mouseParticles ? mouseParticles.querySelectorAll('.mouse-particle') : [];
    
    let mouseX = 0;
    let mouseY = 0;
    let isMoving = false;
    let moveTimeout;

    // Track mouse movement
    contactSection.addEventListener('mousemove', (e) => {
        const rect = contactSection.getBoundingClientRect();
        mouseX = e.clientX - rect.left;
        mouseY = e.clientY - rect.top;
        
        // Update interactive glow position
        if (interactiveGlow) {
            interactiveGlow.style.setProperty('--mouse-x', mouseX + 'px');
            interactiveGlow.style.setProperty('--mouse-y', mouseY + 'px');
            interactiveGlow.classList.add('active');
        }
        
        // Activate mouse particles with smooth animation
        if (mouseParticles && particles.length > 0) {
            particles.forEach((particle, index) => {
                const delay = index * 20; // Reduced delay for smoother effect
                setTimeout(() => {
                    // Use transform for smoother positioning
                    particle.style.transform = `translate(${mouseX}px, ${mouseY}px)`;
                    particle.classList.add('active');
                    
                    // Remove active class after animation
                    setTimeout(() => {
                        particle.classList.remove('active');
                    }, 800); // Match CSS transition duration
                }, delay);
            });
        }
        
        // Set moving state
        isMoving = true;
        clearTimeout(moveTimeout);
        
        moveTimeout = setTimeout(() => {
            isMoving = false;
            if (interactiveGlow) {
                interactiveGlow.classList.remove('active');
            }
        }, 1000);
    });

    // Parallax effect on scroll
    let ticking = false;
    function updateParallax() {
        const scrolled = window.pageYOffset;
        const parallaxWaves = contactSection.querySelectorAll('.parallax-wave');
        
        parallaxWaves.forEach((wave, index) => {
            const speed = 0.5 + (index * 0.1);
            const yPos = -(scrolled * speed);
            wave.style.transform = `translateY(${yPos}px) translateZ(-${10 + index * 10}px)`;
        });
        
        ticking = false;
    }

    function requestTick() {
        if (!ticking) {
            requestAnimationFrame(updateParallax);
            ticking = true;
        }
    }

    window.addEventListener('scroll', requestTick);
}

// Poster Modal Lightbox Functionality
class PosterModal {
    constructor() {
        this.modal = document.getElementById('posterModal');
        this.modalImage = document.getElementById('posterModalImage');
        this.modalTitle = document.getElementById('posterModalTitle');
        this.modalClose = document.getElementById('posterModalClose');
        this.zoomInBtn = document.getElementById('zoomIn');
        this.zoomOutBtn = document.getElementById('zoomOut');
        this.zoomResetBtn = document.getElementById('zoomReset');
        this.zoomLevel = document.getElementById('zoomLevel');
        this.zoomContainer = document.querySelector('.poster-zoom-container');
        
        this.currentZoom = 1;
        this.minZoom = 0.5;
        this.maxZoom = 3;
        this.zoomStep = 0.25;
        
        this.isDragging = false;
        this.startX = 0;
        this.startY = 0;
        this.scrollLeft = 0;
        this.scrollTop = 0;
        
        this.init();
    }
    
    init() {
        // Add click handlers to all programme poster images
        this.addPosterClickHandlers();
        
        // Modal controls
        this.modalClose.addEventListener('click', () => this.closeModal());
        this.zoomInBtn.addEventListener('click', () => this.zoomIn());
        this.zoomOutBtn.addEventListener('click', () => this.zoomOut());
        this.zoomResetBtn.addEventListener('click', () => this.resetZoom());
        
        // Close modal when clicking overlay
        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal || e.target.classList.contains('poster-modal-overlay')) {
                this.closeModal();
            }
        });
        
        // Keyboard controls
        document.addEventListener('keydown', (e) => {
            if (!this.modal.classList.contains('show')) return;
            
            switch(e.key) {
                case 'Escape':
                    this.closeModal();
                    break;
                case '+':
                case '=':
                    e.preventDefault();
                    this.zoomIn();
                    break;
                case '-':
                    e.preventDefault();
                    this.zoomOut();
                    break;
                case '0':
                    e.preventDefault();
                    this.resetZoom();
                    break;
            }
        });
        
        // Mouse wheel zoom disabled - removed for better scrolling experience
        
        // Drag functionality
        this.addDragFunctionality();
    }
    
    addPosterClickHandlers() {
        const posterImages = document.querySelectorAll('.programme-poster-img');
        posterImages.forEach(img => {
            img.addEventListener('click', (e) => {
                e.preventDefault();
                const src = img.src;
                const alt = img.alt;
                const title = alt.replace(' Poster', '');
                this.openModal(src, title);
            });
        });
    }
    
    openModal(imageSrc, title) {
        this.modalImage.src = imageSrc;
        this.modalTitle.textContent = title;
        this.modal.classList.add('show');
        this.resetZoom();
        document.body.style.overflow = 'hidden';
        
        // Ensure image loads and container adjusts
        this.modalImage.onload = () => {
            this.resetZoom();
        };
    }
    
    closeModal() {
        this.modal.classList.remove('show');
        document.body.style.overflow = '';
        this.resetZoom();
    }
    
    zoomIn() {
        if (this.currentZoom < this.maxZoom) {
            this.currentZoom = Math.min(this.currentZoom + this.zoomStep, this.maxZoom);
            this.updateZoom();
        }
    }
    
    zoomOut() {
        if (this.currentZoom > this.minZoom) {
            this.currentZoom = Math.max(this.currentZoom - this.zoomStep, this.minZoom);
            this.updateZoom();
        }
    }
    
    resetZoom() {
        this.currentZoom = 1;
        this.modalImage.style.transform = 'scale(1) translate(0px, 0px)';
        this.zoomLevel.textContent = '100%';
        
        // Update button states
        this.zoomInBtn.disabled = false;
        this.zoomOutBtn.disabled = false;
        this.zoomInBtn.style.opacity = '1';
        this.zoomInBtn.style.cursor = 'pointer';
        this.zoomOutBtn.style.opacity = '1';
        this.zoomOutBtn.style.cursor = 'pointer';
    }
    
    updateZoom() {
        // Get current transform values to preserve position
        const currentTransform = this.modalImage.style.transform;
        let translateX = 0;
        let translateY = 0;
        
        // Extract translate values from current transform
        const translateMatch = currentTransform.match(/translate\(([^,]+),\s*([^)]+)\)/);
        if (translateMatch) {
            translateX = parseFloat(translateMatch[1]);
            translateY = parseFloat(translateMatch[2]);
        }
        
        this.modalImage.style.transform = `scale(${this.currentZoom}) translate(${translateX}px, ${translateY}px)`;
        this.zoomLevel.textContent = `${Math.round(this.currentZoom * 100)}%`;
        
        // Update button states
        this.zoomInBtn.disabled = this.currentZoom >= this.maxZoom;
        this.zoomOutBtn.disabled = this.currentZoom <= this.minZoom;
        
        // Add visual feedback for disabled buttons
        if (this.zoomInBtn.disabled) {
            this.zoomInBtn.style.opacity = '0.5';
            this.zoomInBtn.style.cursor = 'not-allowed';
        } else {
            this.zoomInBtn.style.opacity = '1';
            this.zoomInBtn.style.cursor = 'pointer';
        }
        
        if (this.zoomOutBtn.disabled) {
            this.zoomOutBtn.style.opacity = '0.5';
            this.zoomOutBtn.style.cursor = 'not-allowed';
        } else {
            this.zoomOutBtn.style.opacity = '1';
            this.zoomOutBtn.style.cursor = 'pointer';
        }
    }
    
    addDragFunctionality() {
        let lastX = 0;
        let lastY = 0;
        
        this.modalImage.addEventListener('mousedown', (e) => {
            if (this.currentZoom > 1) {
                this.isDragging = true;
                this.startX = e.clientX - lastX;
                this.startY = e.clientY - lastY;
                this.modalImage.style.cursor = 'grabbing';
                e.preventDefault();
            }
        });
        
        document.addEventListener('mousemove', (e) => {
            if (!this.isDragging) return;
            e.preventDefault();
            
            const deltaX = e.clientX - this.startX;
            const deltaY = e.clientY - this.startY;
            
            lastX = deltaX;
            lastY = deltaY;
            
            this.modalImage.style.transform = `scale(${this.currentZoom}) translate(${deltaX / this.currentZoom}px, ${deltaY / this.currentZoom}px)`;
        });
        
        document.addEventListener('mouseup', () => {
            this.isDragging = false;
            this.modalImage.style.cursor = this.currentZoom > 1 ? 'grab' : 'default';
        });
        
        // Touch support for mobile
        this.modalImage.addEventListener('touchstart', (e) => {
            if (this.currentZoom > 1) {
                this.isDragging = true;
                const touch = e.touches[0];
                this.startX = touch.clientX - lastX;
                this.startY = touch.clientY - lastY;
                e.preventDefault();
            }
        });
        
        document.addEventListener('touchmove', (e) => {
            if (!this.isDragging) return;
            e.preventDefault();
            
            const touch = e.touches[0];
            const deltaX = touch.clientX - this.startX;
            const deltaY = touch.clientY - this.startY;
            
            lastX = deltaX;
            lastY = deltaY;
            
            this.modalImage.style.transform = `scale(${this.currentZoom}) translate(${deltaX / this.currentZoom}px, ${deltaY / this.currentZoom}px)`;
        });
        
        document.addEventListener('touchend', () => {
            this.isDragging = false;
        });
    }
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

// Enhanced Poster Modal with Multiple Images Support
function openPosterModal(element, primaryImage, secondaryImage = '') {
    // Create modal if it doesn't exist
    let modal = document.getElementById('posterModal');
    if (!modal) {
        modal = createPosterModal();
    }
    
    // Get modal elements
    const modalImage = document.getElementById('posterModalImage');
    const modalTitle = document.getElementById('posterModalTitle');
    const zoomContainer = document.querySelector('.poster-zoom-container');
    
    // Set primary image
    modalImage.src = primaryImage;
    modalImage.alt = element.alt;
    modalTitle.textContent = element.alt;
    
    // Clear existing images
    zoomContainer.innerHTML = '';
    
    // Create image container for primary image
    const primaryContainer = document.createElement('div');
    primaryContainer.className = 'poster-image-container';
    primaryContainer.innerHTML = `
        <img src="${primaryImage}" alt="${element.alt}" class="poster-zoom-image" style="max-width: 100%; height: auto;">
    `;
    zoomContainer.appendChild(primaryContainer);
    
    // Add secondary image if available
    if (secondaryImage) {
        const secondaryContainer = document.createElement('div');
        secondaryContainer.className = 'poster-image-container';
        secondaryContainer.innerHTML = `
            <img src="${secondaryImage}" alt="${element.alt} - Secondary" class="poster-zoom-image" style="max-width: 100%; height: auto; margin-top: 2rem;">
        `;
        zoomContainer.appendChild(secondaryContainer);
    }
    
    // Show modal
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    // Initialize zoom functionality
    initPosterZoom(zoomContainer);
}

function createPosterModal() {
    const modalHTML = `
        <div id="posterModal" class="poster-modal">
            <div class="poster-modal-overlay"></div>
            <div class="poster-modal-content">
                <div class="poster-modal-header">
                    <h3 id="posterModalTitle">Programme Poster</h3>
                    <button id="posterModalClose" class="poster-modal-close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="poster-modal-body">
                    <div class="poster-zoom-controls">
                        <button id="zoomIn" class="zoom-btn">
                            <i class="fas fa-search-plus"></i>
                        </button>
                        <button id="zoomOut" class="zoom-btn">
                            <i class="fas fa-search-minus"></i>
                        </button>
                        <button id="zoomReset" class="zoom-btn">
                            <i class="fas fa-expand-arrows-alt"></i>
                        </button>
                        <span id="zoomLevel" class="zoom-level">100%</span>
                    </div>
                    <div class="poster-zoom-container">
                        <!-- Images will be dynamically added here -->
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Add event listeners
    const modal = document.getElementById('posterModal');
    const closeBtn = document.getElementById('posterModalClose');
    const zoomInBtn = document.getElementById('zoomIn');
    const zoomOutBtn = document.getElementById('zoomOut');
    const zoomResetBtn = document.getElementById('zoomReset');
    
    closeBtn.addEventListener('click', () => {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    });
    
    modal.addEventListener('click', (e) => {
        if (e.target === modal || e.target.classList.contains('poster-modal-overlay')) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    });
    
    // Keyboard controls
    document.addEventListener('keydown', (e) => {
        if (!modal.classList.contains('show')) return;
        
        switch(e.key) {
            case 'Escape':
                modal.classList.remove('show');
                document.body.style.overflow = '';
                break;
        }
    });
    
    return modal;
}

function initPosterZoom(container) {
    let currentZoom = 1;
    const minZoom = 0.5;
    const maxZoom = 3;
    const zoomStep = 0.25;
    
    const zoomInBtn = document.getElementById('zoomIn');
    const zoomOutBtn = document.getElementById('zoomOut');
    const zoomResetBtn = document.getElementById('zoomReset');
    const zoomLevel = document.getElementById('zoomLevel');
    
    function updateZoom() {
        const images = container.querySelectorAll('.poster-zoom-image');
        images.forEach(img => {
            img.style.transform = `scale(${currentZoom})`;
        });
        zoomLevel.textContent = Math.round(currentZoom * 100) + '%';
    }
    
    zoomInBtn.addEventListener('click', () => {
        if (currentZoom < maxZoom) {
            currentZoom += zoomStep;
            updateZoom();
        }
    });
    
    zoomOutBtn.addEventListener('click', () => {
        if (currentZoom > minZoom) {
            currentZoom -= zoomStep;
            updateZoom();
        }
    });
    
    zoomResetBtn.addEventListener('click', () => {
        currentZoom = 1;
        updateZoom();
    });
    
    // Initialize zoom level display
    updateZoom();
}

// Initialize all animations when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initFounderStoryVideo();
    initContactAnimations();
    loadFeaturedProgrammes();
    
    // Initialize poster modal if on programmes page
    if (document.getElementById('posterModal')) {
        new PosterModal();
    }
    
    // Initialize trainers carousel if on programmes page
    if (document.getElementById('trainersCarousel')) {
        initTrainersCarousel();
    }
}); // Load Featured Programmes
async function loadFeaturedProgrammes() {
    const container = document.getElementById('featured-programmes-container');
    if (!container) return;

    try {
        const response = await fetch('api/featured-programmes.php');
        const result = await response.json();

        if (result.success && result.data && result.data.length > 0) {
            let html = '<div class="featured-programmes-wrapper" style="display: grid; gap: 20px; margin-bottom: 40px; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">';
            
            result.data.forEach(programme => {
                html += `
                    <div class="programme-card featured-card" style="background: white; border-radius: 12px; padding: 24px; box-shadow: 0 10px 30px rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); position: relative; overflow: hidden; transition: transform 0.3s ease;">
                        <div style="position: absolute; top: 0; right: 0; background: #f59e0b; color: white; padding: 4px 12px; font-size: 12px; font-weight: 600; border-bottom-left-radius: 12px;">
                            <i class="fas fa-star" style="margin-right: 4px;"></i> Featured
                        </div>
                        <h3 style="font-size: 20px; color: #1e293b; margin-top: 10px; margin-bottom: 12px;">${programme.title}</h3>
                        <p style="color: #64748b; font-size: 14px; margin-bottom: 16px; line-height: 1.6; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">${programme.description}</p>
                        
                        <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px;">
                            <span style="background: #f1f5f9; color: #475569; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 500;">
                                <i class="fas fa-clock" style="margin-right: 4px;"></i> ${programme.duration}
                            </span>
                            <span style="background: #f1f5f9; color: #475569; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 500;">
                                <i class="fas fa-tag" style="margin-right: 4px;"></i> ${programme.category}
                            </span>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 16px;">
                            <div>
                                <span style="font-size: 12px; color: #64748b; display: block; margin-bottom: 2px;">Investment</span>
                                <span style="font-size: 18px; font-weight: 700; color: #3b82f6;">${programme.formatted_price}</span>
                            </div>
                            <a href="programme-registration.php?id=${programme.id}" class="btn btn-primary btn-sm" style="padding: 8px 16px; font-size: 14px;">
                                Register Now <i class="fas fa-arrow-right" style="margin-left: 6px;"></i>
                            </a>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }
    } catch (error) {
        console.error('Error loading featured programmes:', error);
    }
}

