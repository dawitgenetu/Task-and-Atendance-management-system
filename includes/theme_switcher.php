<?php
/**
 * Theme Switcher Component
 * 
 * This component provides a reusable theme switcher that can be included
 * in any page. It handles both light and dark mode with smooth transitions.
 */
?>

<!-- Theme Switcher Button -->
<button id="themeToggle" class="theme-switch p-2 text-gray-700 hover:text-gray-900 focus:outline-none transition-colors duration-200" title="Toggle theme">
    <i id="themeIcon" class="fas fa-moon fa-lg"></i>
</button>

<style>
/* Theme switcher styles */
.theme-switch {
    transition: transform 0.3s ease, color 0.2s ease;
    border-radius: 0.5rem;
}

.theme-switch:hover {
    transform: scale(1.1);
    background-color: rgba(0, 0, 0, 0.05);
}

.dark .theme-switch {
    color: var(--text-secondary);
}

.dark .theme-switch:hover {
    color: var(--text-primary);
    background-color: rgba(255, 255, 255, 0.1);
}

/* Animation for theme switch */
@keyframes themeSwitch {
    0% { transform: scale(1) rotate(0deg); }
    50% { transform: scale(0.8) rotate(180deg); }
    100% { transform: scale(1) rotate(360deg); }
}

.theme-switch.animating {
    animation: themeSwitch 0.6s ease-in-out;
}
</style>

<script>
/**
 * Theme Switcher JavaScript
 * Handles theme switching with localStorage persistence and system preference detection
 */
(function() {
    'use strict';
    
    // Theme management class
    class ThemeManager {
        constructor() {
            this.themeToggle = document.getElementById('themeToggle');
            this.themeIcon = document.getElementById('themeIcon');
            this.html = document.documentElement;
            this.currentTheme = 'light';
            
            this.init();
        }
        
        init() {
            // Load saved theme or detect system preference
            this.loadTheme();
            
            // Add event listener
            if (this.themeToggle) {
                this.themeToggle.addEventListener('click', () => this.toggleTheme());
            }
            
            // Listen for system theme changes
            this.watchSystemTheme();
        }
        
        loadTheme() {
            const savedTheme = localStorage.getItem('theme');
            
            if (savedTheme) {
                this.setTheme(savedTheme);
            } else {
                // Check system preference
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const defaultTheme = prefersDark ? 'dark' : 'light';
                this.setTheme(defaultTheme);
                localStorage.setItem('theme', defaultTheme);
            }
        }
        
        setTheme(theme) {
            this.currentTheme = theme;
            this.html.className = theme;
            this.updateIcon(theme);
            localStorage.setItem('theme', theme);
            
            // Dispatch custom event for other components
            window.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme } }));
        }
        
        toggleTheme() {
            const newTheme = this.currentTheme === 'dark' ? 'light' : 'dark';
            this.setTheme(newTheme);
            
            // Add animation
            this.addAnimation();
        }
        
        updateIcon(theme) {
            if (!this.themeIcon) return;
            
            if (theme === 'dark') {
                this.themeIcon.className = 'fas fa-sun fa-lg';
                this.themeToggle.title = 'Switch to light mode';
            } else {
                this.themeIcon.className = 'fas fa-moon fa-lg';
                this.themeToggle.title = 'Switch to dark mode';
            }
        }
        
        addAnimation() {
            if (!this.themeToggle) return;
            
            this.themeToggle.classList.add('animating');
            setTimeout(() => {
                this.themeToggle.classList.remove('animating');
            }, 600);
        }
        
        watchSystemTheme() {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            
            mediaQuery.addEventListener('change', (e) => {
                // Only auto-switch if user hasn't manually set a preference
                if (!localStorage.getItem('theme')) {
                    const newTheme = e.matches ? 'dark' : 'light';
                    this.setTheme(newTheme);
                }
            });
        }
        
        // Public method to get current theme
        getCurrentTheme() {
            return this.currentTheme;
        }
        
        // Public method to check if dark mode is active
        isDarkMode() {
            return this.currentTheme === 'dark';
        }
    }
    
    // Initialize theme manager when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => new ThemeManager());
    } else {
        new ThemeManager();
    }
    
    // Export for global access
    window.ThemeManager = ThemeManager;
})();
</script> 