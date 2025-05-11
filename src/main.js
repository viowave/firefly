import './styles.scss';


// Fetch and display roles
fetch('http://firefly.test/api/roles/')
    .then(response => response.json())
    .then(roles => {
        const requiredRolesDiv = document.getElementById('requiredRoles');
        roles.forEach(role => {
            const button = document.createElement('button');
            button.type = 'button';
            button.classList.add('role-button');
            button.textContent = role.name;
            button.dataset.roleId = role.id; // Store role ID

            // Add click event listener to toggle selected class
            button.addEventListener('click', function () {
                this.classList.toggle('selected');
                updateRoleInput();
            });

            requiredRolesDiv.appendChild(button);
        });
        //create hidden input to store the role ids
        const roleInput = document.createElement('input');
        roleInput.type = 'hidden';
        roleInput.name = 'requiredRoleIds';
        roleInput.id = 'requiredRoleIdsInput';
        document.querySelector('form').appendChild(roleInput);
        updateRoleInput();
    });

function updateRoleInput() {
    const selectedRoles = document.querySelectorAll('.role-button.selected');
    const roleIds = Array.from(selectedRoles).map(button => button.dataset.roleId);
    const roleInput = document.getElementById('requiredRoleIdsInput');
    roleInput.value = roleIds.join(',');
}

// Fetch and display sources
fetch('http://firefly.test/api/sources/')
    .then(response => response.json())
    .then(sources => {
        const targetSourcesDiv = document.getElementById('targetSources');
        const selectAllButton = document.getElementById('selectAllSources');

        sources.forEach(source => {
            const button = document.createElement('button');
            button.type = 'button';
            button.classList.add('source-button');
            button.textContent = source.source_name;
            button.dataset.sourceId = source.source_id;

            button.addEventListener('click', function () {
                this.classList.toggle('selected');
                updateSourceInput();
            });

            targetSourcesDiv.appendChild(button);
        });

        //create hidden input to store source ids
        const sourceInput = document.createElement('input');
        sourceInput.type = 'hidden';
        sourceInput.name = 'targetSourceIds';
        sourceInput.id = 'targetSourceIdsInput';
        document.querySelector('form').appendChild(sourceInput);

        function updateSourceInput() {
            const selectedSources = document.querySelectorAll('.source-button.selected');
            const sourceIds = Array.from(selectedSources).map(button => button.dataset.sourceId);
            const sourceInputElem = document.getElementById('targetSourceIdsInput');
            sourceInputElem.value = sourceIds.join(',');
            //update select all checkbox
            selectAllButton.classList.toggle('selected', selectedSources.length === sources.length);
        }

        // "Select All" functionality
        selectAllButton.addEventListener('click', function () {
            const sourceButtons = document.querySelectorAll('.source-button');
            this.classList.toggle('selected', !this.classList.contains('selected'));
            sourceButtons.forEach(button => {
                button.classList.toggle('selected', this.classList.contains('selected'));
            });
            updateSourceInput();
        });

        //check "Select All" if all sources are selected on load
        const sourceButtons = document.querySelectorAll('.source-button');
        selectAllButton.classList.toggle('selected', sourceButtons.length === sources.length);
        updateSourceInput();
    });

// Draft Leader Toggle Button
const draftLeaderButton = document.getElementById('draftLeaderButton');
const draftLeaderInput = document.getElementById('draftLeaderInput');

draftLeaderButton.addEventListener('click', function () {
    this.classList.toggle('selected');
    draftLeaderInput.value = this.classList.contains('selected') ? '1' : '0';
});

// Number of Players Buttons
const playerCountButtonsDiv = document.getElementById('playerCountButtons');
const numPlayersInput = document.getElementById('numPlayersInput');
const playerCounts = [1, 2, 3, 4, 5]; // Available player counts

playerCounts.forEach(count => {
    const button = document.createElement('button');
    button.type = 'button';
    button.classList.add('number-button');
    button.textContent = count;
    button.dataset.count = count;

    button.addEventListener('click', function () {
        // Remove 'selected' from all number buttons in the group
        Array.from(this.parentElement.children).forEach(b => b.classList.remove('selected'));
        this.classList.add('selected'); // Select clicked button
        numPlayersInput.value = this.dataset.count; // Update hidden input
    });

    playerCountButtonsDiv.appendChild(button);
});
//initialize default selected
document.querySelector(`[data-count="2"]`).classList.add('selected');

// Number of Crew Needed Buttons
const crewNeededButtonsDiv = document.getElementById('crewNeededButtons');
const numCrewNeededInput = document.getElementById('numCrewNeededInput');
const crewCounts = [1, 2, 3, 4, 5]; // Available crew counts

crewCounts.forEach(count => {
    const button = document.createElement('button');
    button.type = 'button';
    button.classList.add('number-button');
    button.textContent = count;
    button.dataset.count = count;

    button.addEventListener('click', function () {
        // Remove 'selected' from all number buttons in the group
        Array.from(this.parentElement.children).forEach(b => b.classList.remove('selected'));
        this.classList.add('selected');
        numCrewNeededInput.value = this.dataset.count;
    });

    crewNeededButtonsDiv.appendChild(button);
});
//initialize default selected
document.querySelector(`[data-count="5"]`).classList.add('selected');
