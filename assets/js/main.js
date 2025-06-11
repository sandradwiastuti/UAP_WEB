document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('transaction-form-container');
    if (!container) {
        return; // Exit if we are not on the transaction form page
    }

    const typeSelect = document.getElementById('type');
    const categorySelect = document.getElementById('category_id');
    
    // Get data passed from PHP
    const allCategories = JSON.parse(container.getAttribute('data-categories'));
    const currentCategoryId = container.getAttribute('data-current-category');

    function populateCategories() {
        const selectedType = typeSelect.value;
        
        // Filter categories based on the selected transaction type
        const filteredCategories = allCategories.filter(category => category.type === selectedType);

        // Clear existing options
        categorySelect.innerHTML = ''; 

        if (filteredCategories.length === 0) {
            const defaultOption = new Option('No categories available for this type', '');
            defaultOption.disabled = true;
            categorySelect.add(defaultOption);
        } else {
             const defaultOption = new Option('Select a category', '');
             categorySelect.add(defaultOption);

            filteredCategories.forEach(category => {
                const option = new Option(category.name, category.id);
                // If editing, pre-select the correct category
                if (category.id == currentCategoryId) {
                    option.selected = true;
                }
                categorySelect.add(option);
            });
        }
    }

    // Add event listener for when the 'type' selection changes
    typeSelect.addEventListener('change', populateCategories);

    // Initial population of categories when the page loads
    populateCategories();
});