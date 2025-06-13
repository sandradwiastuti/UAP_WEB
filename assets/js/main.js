document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('transaction-form-container');
    if (!container) {
        return;
    }

    const typeSelect = document.getElementById('type');
    const categorySelect = document.getElementById('category_id');
    
    const allCategories = JSON.parse(container.getAttribute('data-categories'));
    const currentCategoryId = container.getAttribute('data-current-category');

    function populateCategories() {
        const selectedType = typeSelect.value;
        
        const filteredCategories = allCategories.filter(category => category.type === selectedType);

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
                if (category.id == currentCategoryId) {
                    option.selected = true;
                }
                categorySelect.add(option);
            });
        }
    }

    typeSelect.addEventListener('change', populateCategories);

    populateCategories();
});