/**
 * Theme Toggle - Switch between Catppuccin Mocha (dark) and Latte (light)
 */

(function() {
    'use strict';

    // Check for saved theme preference or default to 'dark'
    const currentTheme = localStorage.getItem('theme') || 'dark';

    // Apply theme on load
    if (currentTheme === 'light') {
        document.body.classList.add('theme-light');
    }

    // Toggle theme function
    function toggleTheme() {
        const isLight = document.body.classList.contains('theme-light');

        if (isLight) {
            document.body.classList.remove('theme-light');
            localStorage.setItem('theme', 'dark');
        } else {
            document.body.classList.add('theme-light');
            localStorage.setItem('theme', 'light');
        }
    }

    // Add toggle button when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        // Create toggle button
        const toggleBtn = document.createElement('button');
        toggleBtn.id = 'theme-toggle';
        toggleBtn.setAttribute('aria-label', 'Toggle theme');
        toggleBtn.innerHTML = '<span class="theme-icon"></span>';

        // Find header navigation list and add button as last item
        const navList = document.querySelector('.wp-block-navigation__container, .wp-block-navigation ul');
        if (navList) {
            // Create a list item wrapper for the button
            const listItem = document.createElement('li');
            listItem.className = 'wp-block-navigation-item theme-toggle-item';
            listItem.appendChild(toggleBtn);
            navList.appendChild(listItem);
        }

        // Add click event
        toggleBtn.addEventListener('click', toggleTheme);
    });
})();
