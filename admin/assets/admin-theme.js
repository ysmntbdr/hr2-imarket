// HR Admin Portal JavaScript Functions

// Sidebar toggle functionality with enhanced animation
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    
    if (window.innerWidth <= 768) {
        // Mobile behavior
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
    } else {
        // Desktop behavior with animation
        sidebar.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
        sidebar.classList.toggle('collapsed');
    }
}

// Add staggered animation to cards
function animateCards() {
    const cards = document.querySelectorAll('.stat-card, .content-card');
    cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
    });
}

// Add ripple effect to buttons
function addRippleEffect(element) {
    element.addEventListener('click', function(e) {
        const ripple = document.createElement('span');
        const rect = this.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = e.clientX - rect.left - size / 2;
        const y = e.clientY - rect.top - size / 2;
        
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = x + 'px';
        ripple.style.top = y + 'px';
        ripple.classList.add('ripple');
        
        this.appendChild(ripple);
        
        setTimeout(() => {
            ripple.remove();
        }, 600);
    });
}

// Smooth scroll for internal links
function smoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

// Close sidebar when clicking overlay
document.addEventListener('DOMContentLoaded', function() {
    const overlay = document.getElementById('mobileOverlay');
    if (overlay) {
        overlay.addEventListener('click', function() {
            toggleSidebar();
        });
    }

    // Add active state to current page
    const currentPage = window.location.pathname.split('/').pop();
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        if (link.getAttribute('href') === currentPage) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });

    // Initialize animations
    animateCards();
    
    // Add ripple effect to buttons
    document.querySelectorAll('.btn, .nav-link').forEach(addRippleEffect);
    
    // Initialize smooth scrolling
    smoothScroll();
    
    // Add hover effects to stat values
    document.querySelectorAll('.stat-value').forEach(stat => {
        stat.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.1)';
            this.style.transition = 'transform 0.3s ease';
        });
        
        stat.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
});
