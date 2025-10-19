class PortfolioPresentation {
    constructor() {
        this.pages = [
            { url: './profile.html', duration: 1000, animation: 'slide-right' },
            { url: './experience.html', duration: 1000, animation: 'slide-left' },
            { url: './education.html', duration: 1000, animation: 'slide-up' },
            { url: './skills.html', duration: 1000, animation: 'zoom-in' },
            { url: './certifications.html', duration: 1000, animation: 'fade' },
            { url: './languages.html', duration: 1000, animation: 'slide-up' }
        ];
        this.currentIndex = 0;
        this.isPresenting = false;
    }

    async startPresentation() {
        if (this.isPresenting) return;
        this.isPresenting = true;

        // Create overlay with modern blur effect
        const overlay = document.createElement('div');
        overlay.className = 'presentation-overlay';
        document.body.appendChild(overlay);

        // Create modern loading spinner
        const loading = document.createElement('div');
        loading.className = 'presentation-loading';
        loading.innerHTML = `
            <div class="spinner">
                <div class="bounce1"></div>
                <div class="bounce2"></div>
                <div class="bounce3"></div>
            </div>
            <div class="loading-text">Loading</div>
        `;
        overlay.appendChild(loading);

        // Create content container with 3D perspective
        const container = document.createElement('div');
        container.className = 'presentation-container';
        container.style.display = 'none';
        overlay.appendChild(container);

        // Add progress indicator
        const progressContainer = document.createElement('div');
        progressContainer.className = 'progress-container';
        const progressDots = this.pages.map((_, index) => {
            const dot = document.createElement('div');
            dot.className = 'progress-dot';
            return dot;
        });
        progressDots.forEach(dot => progressContainer.appendChild(dot));
        overlay.appendChild(progressContainer);

        // Add modern close button
        const closeButton = document.createElement('button');
        closeButton.className = 'presentation-close';
        closeButton.innerHTML = `
            <svg viewBox="0 0 24 24" width="24" height="24">
                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
            </svg>
        `;
        closeButton.onclick = () => {
            this.isPresenting = false;
            this.endPresentation(overlay);
        };
        overlay.appendChild(closeButton);

        // Navigation arrows
        const createNavButton = (direction) => {
            const btn = document.createElement('button');
            btn.className = `nav-button nav-${direction}`;
            btn.innerHTML = direction === 'prev' ? '‹' : '›';
            return btn;
        };
        const prevButton = createNavButton('prev');
        const nextButton = createNavButton('next');
        overlay.appendChild(prevButton);
        overlay.appendChild(nextButton);

        // Start cycling through pages
        for (let i = 0; i < this.pages.length; i++) {
            if (!this.isPresenting) break;
            
            const page = this.pages[i];
            try {
                loading.style.display = 'flex';
                container.style.display = 'none';

                const response = await fetch(page.url);
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const html = await response.text();
                
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const mainContent = doc.querySelector('main');
                
                if (!mainContent) throw new Error(`No main content found in ${page.url}`);

                // Update progress dots
                progressDots.forEach((dot, index) => {
                    dot.classList.toggle('active', index === i);
                });

                // Hide loading, show container with animation
                loading.style.display = 'none';
                container.style.display = 'block';
                container.className = `presentation-container ${page.animation}`;
                container.innerHTML = mainContent.innerHTML;

                // Wait for duration
                await new Promise(r => setTimeout(r, page.duration));
            } catch (error) {
                console.error(`Error loading ${page.url}:`, error);
                container.innerHTML = `
                    <div class="error-message">
                        <svg viewBox="0 0 24 24" width="48" height="48">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                        </svg>
                        <h3>Oops! Something went wrong</h3>
                        <p>${error.message}</p>
                    </div>`;
                await new Promise(r => setTimeout(r, 2000));
            }
        }

        if (this.isPresenting) {
            setTimeout(() => {
                this.isPresenting = false;
                this.endPresentation(overlay);
            }, 1000);
        }
    }

    endPresentation(overlay) {
        overlay.classList.add('fade-out');
        setTimeout(() => {
            overlay.remove();
            this.isPresenting = false;
        }, 500);
    }
}

function startPresentation() {
    const pages = document.querySelectorAll('nav ul li a');
    let currentIndex = 0;
    let isPresenting = true;

    // Create presentation overlay
    const overlay = document.createElement('div');
    overlay.className = 'presentation-overlay';
    document.body.appendChild(overlay);

    // Create content container
    const container = document.createElement('div');
    container.className = 'presentation-container';
    overlay.appendChild(container);

    // Create progress bar
    const progress = document.createElement('div');
    progress.className = 'presentation-progress';
    overlay.appendChild(progress);

    // Add close button
    const closeBtn = document.createElement('button');
    closeBtn.className = 'presentation-close';
    closeBtn.innerHTML = '×';
    closeBtn.onclick = endPresentation;
    overlay.appendChild(closeBtn);

    // Navigation controls
    const prevBtn = document.createElement('button');
    prevBtn.className = 'nav-btn prev';
    prevBtn.innerHTML = '‹';
    prevBtn.onclick = () => showPage(currentIndex - 1);
    overlay.appendChild(prevBtn);

    const nextBtn = document.createElement('button');
    nextBtn.className = 'nav-btn next';
    nextBtn.innerHTML = '›';
    nextBtn.onclick = () => showPage(currentIndex + 1);
    overlay.appendChild(nextBtn);

    async function showPage(index) {
        if (index < 0 || index >= pages.length || !isPresenting) return;

        currentIndex = index;
        const link = pages[index];
        const url = link.getAttribute('href');

        try {
            const response = await fetch(url);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const html = await response.text();

            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const content = doc.querySelector('main');

            if (!content) throw new Error('No main content found');

            // Update container with animation
            container.style.opacity = '0';
            setTimeout(() => {
                container.innerHTML = content.innerHTML;
                container.style.opacity = '1';
            }, 300);

            // Update progress
            progress.style.width = `${((index + 1) / pages.length) * 100}%`;

            // Update navigation buttons
            prevBtn.style.display = index === 0 ? 'none' : 'block';
            nextBtn.style.display = index === pages.length - 1 ? 'none' : 'block';

            // Auto-advance after delay
            if (isPresenting) {
                setTimeout(() => {
                    if (currentIndex < pages.length - 1) {
                        showPage(currentIndex + 1);
                    } else {
                        endPresentation();
                    }
                }, 5000);
            }
        } catch (error) {
            console.error('Error loading page:', error);
            container.innerHTML = `
                <div class="error-message">
                    <h3>Failed to load content</h3>
                    <p>
                        ${error.message}<br>
                        <strong>Are you running a local server?</strong><br>
                        <small>Open your site with http://santoshchowdhury.my-style.in, not file:///</small>
                    </p>
                </div>`;
        }
    }

    function endPresentation() {
        isPresenting = false;
        overlay.classList.add('fade-out');
        setTimeout(() => overlay.remove(), 500);
    }

    // Start the presentation
    showPage(0);
}