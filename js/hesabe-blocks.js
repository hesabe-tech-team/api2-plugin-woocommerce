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
console.log('Hesabe Payment Methods:', paymentMethods);
    let selectedPaymentType = paymentMethods[0]?.id || '0';

    const Content = () => {
        const [selected, setSelected] = useState(selectedPaymentType);

        const onChange = (id) => {
            selectedPaymentType = id;
            setSelected(id);
            const hidden = document.getElementById('hesabe_selected_payment_type');
            if (hidden) {
                hidden.value = method.id;
            }

        };

        return el(
            'div',
            null,
            // Hidden field to store selected payment
            el('input', {
                type: 'hidden',
                id: 'hesabe_selected_payment_type',
                name: 'hesabe_selected_payment_type',
                value: selected || '0', // default to "0" if nothing selected
            }),
            // Render radio buttons
            paymentMethods.map((method) =>
                el(
                    'label',
                    { key: method.id, style: { display: 'block' } },
                    el('input', {
                        type: 'radio',
                        name: 'payment_option', // important for radio group
                        value: method.id,
                        checked: selected === method.id,
                        onChange: () => {
                            // Update selected state in React
                            onChange(method.id);
                            // Update hidden input value to submit with form
                            const hidden = document.getElementById('hesabe_selected_payment_type');
                            if (hidden) {
                                hidden.value = method.id;
                            }
                        },
                    }),
                    ' ',
                    method.name
                )
            )
        );
    };

    registry.registerPaymentMethod({
		name: 'hesabe',
		label: settings.title || 'Hesabe',
		ariaLabel: settings.title || 'Hesabe payment method', // ✅ REQUIRED
		content: el(Content),
		edit: el(Content),
		canMakePayment: () => true,

		supports: {
			features: ['products'],
		},

		getPaymentMethodData: () => ({
			hesabe_selected_payment_type: selectedPaymentType,
		}),
	});
})(window.wp);
