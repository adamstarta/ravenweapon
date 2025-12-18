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

            // Add color column
            columns.splice(insertPosition, 0, {
                property: 'payload.selectedColor',
                label: 'Farbe',
                allowResize: true,
                width: '120px',
                rawData: true
            });

            return columns;
        }
    },

    methods: {
        getSelectedColor(item) {
            if (item.payload && item.payload.selectedColor) {
                return item.payload.selectedColor;
            }
            return '-';
        }
    }
});
