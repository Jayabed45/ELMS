// Get all tab buttons and content divs
        const tabButtons = document.querySelectorAll('a[id$="-tab"]');
        const tabContents = document.querySelectorAll('.tab-content');
        
        // Function to show selected tab content and hide others
        function showTab(tabId) {
            // Hide all tab contents
            tabContents.forEach(content => {
                content.classList.add('hidden');
            });
            
            // Remove active class from all tab buttons
            tabButtons.forEach(button => {
                button.classList.remove('bg-indigo-800');
                button.classList.add('text-indigo-100', 'hover:bg-indigo-700');
            });
            
            // Show selected tab content
            const selectedContent = document.getElementById(tabId + '-content');
            if (selectedContent) {
                selectedContent.classList.remove('hidden');
            }
            
            // Set active class on selected tab button
            const selectedButton = document.getElementById(tabId + '-tab');
            if (selectedButton) {
                selectedButton.classList.remove('text-indigo-100', 'hover:bg-indigo-700');
                selectedButton.classList.add('bg-indigo-800');
            }
        }
        
        // Set up click handlers for all tab buttons
        tabButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const tabId = this.getAttribute('href').substring(1);
                showTab(tabId);
            });
        });
        
        // Initialize: show the dashboard tab by default
        showTab('dashboard');
        
        // Show leave tab if there are pending requests and URL hash is #leave
        if (window.location.hash === '#leave') {
            showTab('leave');
        }