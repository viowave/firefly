import './styles.scss';

// Configuration
const CONFIG = {
    DEFAULT_PLAYER_COUNT: 2,
    DEFAULT_CREW_NEEDED: 1
};

if (window.APP_ENV === 'production') {
    CONFIG.API_BASE_URL = 'http://cheatersnever.win/firefly'; // Replace with your production URL
} else {
    CONFIG.API_BASE_URL = process.env.API_BASE_URL || 'http://firefly.test';
}

class FireflySetup {
    constructor() {
        this.form = document.querySelector('form');
        this.elements = {
            roles: document.getElementById('requiredRoles'),
            sources: document.getElementById('targetSources'),
            playerCount: document.getElementById('playerCountButtons'),
            crewNeeded: document.getElementById('crewNeededButtons'),
            draftLeader: document.getElementById('draftLeaderButton'),
            selectAllSources: document.getElementById('selectAllSources'),
            playerNames: document.getElementById('playerNames'), // Added container for player name inputs
            resultsContainer: document.querySelector('.resultsWrapper'), // Or create a new container if you prefer
            initialWrapper: document.querySelector('.wrapper')

        };

        this.inputs = {
            roles: this.createHiddenInput('requiredRoleIds'),
            sources: this.createHiddenInput('targetSourceIds'),
            playerCount: document.getElementById('numPlayersInput'),
            crewNeeded: document.getElementById('numCrewNeededInput'),
            draftLeader: document.getElementById('draftLeaderInput'),
            // playerNames: []  // Removed, we'll manage them directly
        };

        this.data = {
            roles: [],
            sources: []
        };

        this.init();
    }

    get maxSelectableRoles() {
        return parseInt(this.inputs.crewNeeded.value, 10) || CONFIG.DEFAULT_CREW_NEEDED;
    }

    async init() {
        try {
            await Promise.all([
                this.fetchRoles(),
                this.fetchSources()
            ]);

            this.initToggleGroup(this.elements.playerCount, this.inputs.playerCount,
                [1, 2, 3, 4, 5], CONFIG.DEFAULT_PLAYER_COUNT, (value) => this.updatePlayerNameInputs(value)); // Pass callback

            this.initToggleGroup(this.elements.crewNeeded, this.inputs.crewNeeded,
                [1, 2, 3, 4, 5], CONFIG.DEFAULT_CREW_NEEDED);

            this.initDraftLeaderToggle();
            this.form.addEventListener('submit', (event) => this.handleFormSubmit(event)); // Changed event listener
            // this.form.addEventListener('submit', (event) => this.validateForm(event)); // Keep this for validation before submit

        } catch (error) {
            this.showError('Failed to initialize setup. Please try refreshing the page.');
            console.error('Initialization error:', error);
        }
    }

    createHiddenInput(name) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.id = `${name}Input`;
        input.value = '';
        this.form.appendChild(input);
        return input;
    }

    createToggleButton(text, className, datasetKey, datasetValue, onClick) {
        const button = document.createElement('button');
        button.type = 'button';
        button.classList.add(className);
        button.textContent = text;
        button.dataset[datasetKey] = datasetValue;
        button.setAttribute('aria-pressed', 'false');
        button.addEventListener('click', onClick);
        return button;
    }

    async fetchData(endpoint) {
        try {
            this.showLoading(endpoint);
            const response = await fetch(`${CONFIG.API_BASE_URL}/api/${endpoint}/`);

            if (!response.ok) {
                throw new Error(`API error: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error(`Error fetching ${endpoint}:`, error);
            this.showError(`Failed to load ${endpoint}. Please try again.`);
            return [];
        } finally {
            this.hideLoading(endpoint);
        }
    }

    async fetchRoles() {
        this.data.roles = await this.fetchData('roles');
        this.renderRoles();
    }

    async fetchSources() {
        this.data.sources = await this.fetchData('sources');
        this.renderSources();
    }

    renderRoles() {
        const container = this.elements.roles;

        this.data.roles.forEach(role => {
            const btn = this.createToggleButton(role.name, 'role-button', 'roleId', role.id, () => {
                const isSelected = btn.classList.contains('selected');
                const selectedCount = document.querySelectorAll('.role-button.selected').length;
                const maxSelectable = this.maxSelectableRoles;

                if (!isSelected && selectedCount >= maxSelectable) {
                    this.showError(`You can only select up to ${maxSelectable} role(s).`);
                    return;
                }

                btn.classList.toggle('selected');
                btn.setAttribute('aria-pressed', !isSelected);
                this.updateInputFromSelection('.role-button.selected', 'roleId', this.inputs.roles);
            });
            container.appendChild(btn);
        });
    }

    renderSources() {
        const container = this.elements.sources;

        this.data.sources.forEach((source, index) => {
            const btn = this.createToggleButton(
                source.source_name,
                'source-button',
                'sourceId',
                source.source_id,
                () => {
                    btn.classList.toggle('selected');
                    btn.setAttribute('aria-pressed', btn.classList.contains('selected'));
                    this.updateSourceInput();
                }
            );
            container.appendChild(btn);

            if (index === 0) {
                btn.classList.add('selected');
                btn.setAttribute('aria-pressed', 'true');
            }
        });

        this.elements.selectAllSources.addEventListener('click', () => {
            const selected = !this.elements.selectAllSources.classList.contains('selected');

            document.querySelectorAll('.source-button').forEach(btn => {
                btn.classList.toggle('selected', selected);
                btn.setAttribute('aria-pressed', selected);
            });

            this.elements.selectAllSources.classList.toggle('selected', selected);
            this.elements.selectAllSources.setAttribute('aria-pressed', selected);

            this.updateSourceInput();
        });

        this.updateSourceInput();
    }

    updateInputFromSelection(selector, dataKey, inputElement) {
        const selected = [...document.querySelectorAll(selector)]
            .map(btn => btn.dataset[dataKey]);

        inputElement.value = selected.join(',');
    }

    updateSourceInput() {
        this.updateInputFromSelection('.source-button.selected', 'sourceId', this.inputs.sources);

        const allSelected = document.querySelectorAll('.source-button.selected').length ===
                            this.data.sources.length;

        this.elements.selectAllSources.classList.toggle('selected', allSelected);
        this.elements.selectAllSources.setAttribute('aria-pressed', allSelected);
    }

    initToggleGroup(container, input, options, defaultVal, callback = null) { // Added callback
        options.forEach(value => {
            const btn = this.createToggleButton(
                value.toString(),
                'number-button',
                'count',
                value.toString(),
                () => {
                    container.querySelectorAll('button').forEach(b => {
                        b.classList.remove('selected');
                        b.setAttribute('aria-pressed', 'false');
                    });

                    btn.classList.add('selected');
                    btn.setAttribute('aria-pressed', 'true');
                    input.value = value;

                    // If this is the crewNeeded container, trim selected roles if needed
                    if (container === this.elements.crewNeeded) {
                        const selected = [...document.querySelectorAll('.role-button.selected')];
                        const limit = this.maxSelectableRoles;
                        if (selected.length > limit) {
                            selected.slice(limit).forEach(btn => {
                                btn.classList.remove('selected');
                                btn.setAttribute('aria-pressed', 'false');
                            });
                            this.updateInputFromSelection('.role-button.selected', 'roleId', this.inputs.roles);
                        }
                    }

                    if (callback) { // Execute the callback if provided
                        callback(value);
                    }
                }
            );
            container.appendChild(btn);
        });

        // Set default
        const defaultBtn = container.querySelector(`[data-count="${defaultVal}"]`);
        if (defaultBtn) {
            defaultBtn.classList.add('selected');
            defaultBtn.setAttribute('aria-pressed', 'true');
            input.value = defaultVal;
             if (callback) {  //initial call.
                callback(defaultVal);
            }
        }
    }

    initDraftLeaderToggle() {
        const button = this.elements.draftLeader;
        const input = this.inputs.draftLeader;

        input.value = '0';
        button.setAttribute('aria-pressed', 'false');

        button.addEventListener('click', () => {
            const isSelected = button.classList.toggle('selected');
            button.setAttribute('aria-pressed', isSelected);
            input.value = isSelected ? '1' : '0';
        });
    }

    showLoading(elementName) {
        const container = this.elements[elementName];
        if (container) {
            container.classList.add('loading');
            const loadingEl = document.createElement('div');
            loadingEl.className = 'loading-indicator';
            loadingEl.textContent = 'Loading...';
            container.appendChild(loadingEl);
        }
    }

    hideLoading(elementName) {
        const container = this.elements[elementName];
        if (container) {
            container.classList.remove('loading');
            const indicator = container.querySelector('.loading-indicator');
            if (indicator) {
                indicator.remove();
            }
        }
    }

    showError(message) {
        const errorContainer = document.createElement('div');
        errorContainer.className = 'error-message';
        errorContainer.textContent = message;

        // Insert at top of form
        this.form.insertBefore(errorContainer, this.form.firstChild);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            errorContainer.remove();
        }, 5000);
    }

    validateForm(event) {
        // Clear any existing error message
        const existingError = this.form.querySelector('.error-message');
        if (existingError) {
            existingError.remove();
        }

        const selectedRolesCount = document.querySelectorAll('.role-button.selected').length;
        const maxSelectableRoles = this.maxSelectableRoles;
        const playerCount = parseInt(this.inputs.playerCount.value, 10);

        for (let i = 1; i <= playerCount; i++) {
            const playerNameInput = document.getElementById(`playerName${i}`);
            if (!playerNameInput || !playerNameInput.value.trim()) {
                event.preventDefault();
                this.showError(`Please enter a name for Player ${i}.`);
                return;
            }
        }

        if (selectedRolesCount > maxSelectableRoles) {
            event.preventDefault(); // Prevent form submission
            this.showError(`You have selected ${selectedRolesCount} roles, but can only select a maximum of ${maxSelectableRoles}.`);
            return; // Stop form validation and submission
        }
    }

    updatePlayerNameInputs(playerCount) {
        const playerNamesContainer = this.elements.playerNames;
        playerNamesContainer.innerHTML = ''; // Clear previous inputs

        // this.inputs.playerNames = []; //reset. Not used, managed directly.

        for (let i = 1; i <= playerCount; i++) {
            const label = document.createElement('label');
            label.textContent = `Player Name ${i}:`;
            label.setAttribute('for', `playerName${i}`);

            const input = document.createElement('input');
            input.type = 'text';
            input.id = `playerName${i}`;
            input.name = `playerName${i}`;
            input.className = 'player-name-input';

            const div = document.createElement('div'); //wrap
            div.className = 'player-name-input-group';
            div.appendChild(label);
            div.appendChild(input);

            playerNamesContainer.appendChild(div);
        }
    }

    getFormData() {
        const formData = new FormData(this.form); // Use FormData to easily collect form data
        return formData;
    }

async handleFormSubmit(event) {
    event.preventDefault();
    this.validateForm(event);

    const formData = this.getFormData();
    const resultsContainer = this.elements.resultsContainer;
    const loadingOverlay = document.getElementById('loading-overlay');

    // Clear previous results immediately
    if (resultsContainer) {
        resultsContainer.innerHTML = '';
    }

    try {
        // Show the loading overlay IMMEDIATELY and it will be opaque
        if (loadingOverlay) {
            loadingOverlay.classList.add('active');
        }
        this.showLoading('resultsContainer'); // You might want to adjust this

        // Scroll to the top while the overlay is visible
        window.scrollTo(0, 0);

        const response = await fetch('run_draft.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const resultsHTML = await response.text();

        // Small delay before displaying results (optional)
        await new Promise(resolve => setTimeout(resolve, 100));

        if (resultsContainer) {
            resultsContainer.innerHTML = resultsHTML;
            if (this.elements.initialWrapper) {
                this.elements.initialWrapper.remove();
            }

            // Delay before fading out the overlay
            await new Promise(resolve => setTimeout(resolve, 500));

            // Hide the loading overlay by fading it out
            if (loadingOverlay) {
                loadingOverlay.classList.remove('active'); // Fade out
            }
        } else {
            console.error('Error: .resultsWrapper container not found in the DOM.');
            this.showError('Failed to display results. Please try again.');
        }

    } catch (error) {
        console.error('Error submitting form:', error);
        this.showError('Failed to run the draft. Please try again.');
    } finally {
        // Ensure the overlay is hidden in case of error
        if (loadingOverlay) {
            loadingOverlay.classList.remove('active');
        }
        this.hideLoading('resultsContainer'); // You might want to adjust this
    }
}r

    showLoading(elementName) {
        // You might want to adjust the behavior of your original loading indicator
        // For example, you could disable the form or show a smaller indicator.
        const container = this.elements[elementName];
        if (container && !document.getElementById('loading-overlay')) { // Only show if the overlay isn't active
            container.classList.add('loading');
            const loadingEl = document.createElement('div');
            loadingEl.className = 'loading-indicator';
            loadingEl.textContent = 'Loading...';
            container.appendChild(loadingEl);
        }
    }

    hideLoading(elementName) {
        // Ensure the overlay is hidden regardless of the original loading indicator
        const container = this.elements[elementName];
        if (container) {
            container.classList.remove('loading');
            const indicator = container.querySelector('.loading-indicator');
            if (indicator) {
                indicator.remove();
            }
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.fireflySetup = new FireflySetup();
});