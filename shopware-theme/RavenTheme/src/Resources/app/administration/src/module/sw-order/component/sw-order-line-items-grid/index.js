import template from './sw-order-line-items-grid.html.twig';

const { Component } = Shopware;

Component.override('sw-order-line-items-grid', {
    template,
    computed: {
        getLineItemColumns() {
            const columns = this.$super('getLineItemColumns');

            // Find position after label column (usually index 1)
            const labelIndex = columns.findIndex(col => col.property === 'label');
            const insertPosition = labelIndex !== -1 ? labelIndex + 1 : 1;

            // Add variant column (combined color + size)
            columns.splice(insertPosition, 0, {
                property: 'payload.variantenDisplay',
                label: 'Variante',
                allowResize: true,
                width: '150px',
                rawData: true
            });

            return columns;
        }
    },

    methods: {
        getVariantenDisplay(item) {
            if (item.payload) {
                // First check combined variantenDisplay
                if (item.payload.variantenDisplay) {
                    return item.payload.variantenDisplay;
                }
                // Fall back to individual fields
                if (item.payload.selectedColor && item.payload.selectedSize) {
                    return item.payload.selectedColor + ' / ' + item.payload.selectedSize;
                }
                if (item.payload.selectedColor) {
                    return item.payload.selectedColor;
                }
                if (item.payload.selectedSize) {
                    return item.payload.selectedSize;
                }
            }
            return '-';
        }
    }
});
