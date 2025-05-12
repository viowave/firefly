import './styles.scss';

function createToggleButton(text, className, datasetKey, datasetValue, onClick) {
    const button = document.createElement('button');
    button.type = 'button';
    button.classList.add(className);
    button.textContent = text;
    button.dataset[datasetKey] = datasetValue;
    button.addEventListener('click', onClick);
    return button;
}

function initRoles() {
    fetch('http://firefly.test/api/roles/')
        .then(res => res.json())
        .then(roles => {
            const roleContainer = document.getElementById('requiredRoles');
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'requiredRoleIds';
            hiddenInput.id = 'requiredRoleIdsInput';
            document.querySelector('form').appendChild(hiddenInput);

            roles.forEach(role => {
                const btn = createToggleButton(role.name, 'role-button', 'roleId', role.id, () => {
                    btn.classList.toggle('selected');
                    updateInputFromSelection('.role-button.selected', 'roleId', 'requiredRoleIdsInput');
                });
                roleContainer.appendChild(btn);
            });
        });
}

function initSources() {
    fetch('http://firefly.test/api/sources/')
        .then(res => res.json())
        .then(sources => {
            const sourceContainer = document.getElementById('targetSources');
            const selectAllBtn = document.getElementById('selectAllSources');
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'targetSourceIds';
            hiddenInput.id = 'targetSourceIdsInput';
            document.querySelector('form').appendChild(hiddenInput);

            sources.forEach(source => {
                const btn = createToggleButton(source.source_name, 'source-button', 'sourceId', source.source_id, () => {
                    btn.classList.toggle('selected');
                    updateSourceInput();
                });
                sourceContainer.appendChild(btn);
            });

            function updateSourceInput() {
                updateInputFromSelection('.source-button.selected', 'sourceId', 'targetSourceIdsInput');
                const allSelected = document.querySelectorAll('.source-button.selected').length === sources.length;
                selectAllBtn.classList.toggle('selected', allSelected);
            }

            selectAllBtn.addEventListener('click', () => {
                const selected = !selectAllBtn.classList.contains('selected');
                document.querySelectorAll('.source-button').forEach(btn =>
                    btn.classList.toggle('selected', selected)
                );
                updateSourceInput();
            });

            updateSourceInput(); // Initialize
        });
}

function updateInputFromSelection(selector, dataKey, inputId) {
    const selected = [...document.querySelectorAll(selector)].map(btn => btn.dataset[dataKey]);
    document.getElementById(inputId).value = selected.join(',');
}

function initToggleGroup(containerId, inputId, options, defaultVal) {
    const container = document.getElementById(containerId);
    const input = document.getElementById(inputId);
    options.forEach(count => {
        const btn = createToggleButton(count, 'number-button', 'count', count, function () {
            container.querySelectorAll('button').forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
            input.value = count;
        });
        container.appendChild(btn);
    });
    container.querySelector(`[data-count="${defaultVal}"]`).classList.add('selected');
    input.value = defaultVal;
}

function initDraftLeaderToggle() {
    const button = document.getElementById('draftLeaderButton');
    const input = document.getElementById('draftLeaderInput');
    button.addEventListener('click', function () {
        this.classList.toggle('selected');
        input.value = this.classList.contains('selected') ? '1' : '0';
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initRoles();
    initSources();
    initToggleGroup('playerCountButtons', 'numPlayersInput', [1, 2, 3, 4, 5], 2);
    initToggleGroup('crewNeededButtons', 'numCrewNeededInput', [1, 2, 3, 4, 5], 5);
    initDraftLeaderToggle();
});
