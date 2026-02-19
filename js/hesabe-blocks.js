(function (wp) { 
    const registry =
        window.wc?.wcBlocksRegistry ||
        window.wcBlocksRegistry;

    if (!registry?.registerPaymentMethod) {
        console.error('Hesabe: registerPaymentMethod not found');
        return;
    }

    const { createElement: el, useState } = wp.element;

    const settings = window.wcHesabeBlocksData?.hesabe_data || {};
    const paymentMethods = settings.paymentMethods || [];
    if (!paymentMethods.length) {
        console.warn('Hesabe: No payment methods enabled');
    }

    let selectedPaymentType = paymentMethods[0]?.id || '0';

    const Content = () => {
        const [selected, setSelected] = useState(selectedPaymentType);

        const onChange = (id) => {
            selectedPaymentType = id;
            setSelected(id);

            // Update hidden input for Store API
            const hidden = document.getElementById('hesabe_selected_payment_type');
            if (hidden) {
                hidden.value = id;
                // Trigger change event so Blocks / Store API picks it up
                hidden.dispatchEvent(new Event('change', { bubbles: true }));
            }

            // ✅ Add/update the payment_data field for WooCommerce Blocks
            let paymentDataField = document.querySelector('input[name="wc-hesabe-selected-payment-type"]');
            if (paymentDataField) {
                paymentDataField.value = id;
            } else {
                paymentDataField = document.createElement('input');
                paymentDataField.type = 'hidden';
                paymentDataField.name = 'wc-hesabe-selected-payment-type';
                paymentDataField.value = id;
                document.querySelector('form.checkout').appendChild(paymentDataField);
            }
        };

        return el(
            'div',
            null,
            // Hidden input for Store API
            el('input', {
                type: 'hidden',
                id: 'hesabe_selected_payment_type',
                name: 'hesabe_selected_payment_type',
                value: selected || '0',
            }),
            // Render radio buttons for enabled methods
            paymentMethods.map((method) =>
                el(
                    'label',
                    { key: method.id, style: { display: 'block', cursor: 'pointer' } },
                    el('input', {
                        type: 'radio',
                        name: 'payment_option',
                        value: method.id,
                        checked: selected === method.id,
                        onChange: () => onChange(method.id),
                        style: { marginRight: '8px' },
                    }),
                    method.name
                )
            )
        );
    };

    registry.registerPaymentMethod({
        name: 'hesabe',
        label: settings.title || 'Hesabe',
        ariaLabel: settings.title || 'Hesabe payment method',
        content: el(Content),
        edit: el(Content),
        canMakePayment: () => paymentMethods.length > 0,
        supports: { features: ['products'] },
        getPaymentMethodData: () => ({
            hesabe_selected_payment_type: selectedPaymentType,
        }),
    });
})(window.wp);
