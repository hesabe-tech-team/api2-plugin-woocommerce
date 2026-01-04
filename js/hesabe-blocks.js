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

    let selectedPaymentType = paymentMethods[0]?.id || '0';

    const Content = () => {
        const [selected, setSelected] = useState(selectedPaymentType);

        const onChange = (id) => {
            selectedPaymentType = id;
            setSelected(id);
        };

        return el(
            'div',
            null,
            paymentMethods.map((method) =>
                el(
                    'label',
                    { key: method.id, style: { display: 'block' } },
                    el('input', {
                        type: 'radio',
                        value: method.id,
                        checked: selected === method.id,
                        onChange: () => onChange(method.id),
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
