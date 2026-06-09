const KomojuPaymentModule = (() => {
    const { useEffect, useCallback, useRef, createElement } = window.wp.element;

    function registerPaymentMethod(paymentMethod) {
        const name = `${paymentMethod.id}`
        const settings = window.wc.wcSettings.getSetting(`${name}_data`, {});
        const description = window.wp.htmlEntities.decodeEntities(window.wp.i18n.__(settings.description, 'komoju_woocommerce'));
        let descriptionDiv = null;
        if (description) {
            descriptionDiv = createElement('div',
                {
                    id: `${name}_description`,
                    style: { display: 'none', alignItems: 'center', justifyContent: 'center', width: '100%' }
                },
                description
            );
        }

        const label = createElement('div', {
            style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', width: '95%', flexWrap: 'wrap' }
        },
            createElement('span', { style: { width: 'auto' } }, window.wp.htmlEntities.decodeEntities(settings.title || window.wp.i18n.__('title', 'komoju_woocommerce'))),
            settings.icon ?
                createElement('img', {
                    src: settings.icon,
                    alt: settings.title || 'Payment Method Icon',
                    style: { display: 'flex', alignItems: 'center', justifyContent: 'center', marginLeft: '10px' }
                }) : null,
            descriptionDiv
        );

        const KomojuComponent = ({ activePaymentMethod, emitResponse, eventRegistration }) => {
            const { onPaymentSetup } = eventRegistration;
            const komojuFieldEnabledMethods = ['komoju_credit_card', 'komoju_konbini', 'komoju_bank_transfer']

            const testModeNotice = settings.testMode
                ? createElement('p', {
                    style: { background: '#fff8e5', border: '1px solid #f0b849', borderRadius: '4px', padding: '8px 12px', marginBottom: '10px', fontSize: '13px' }
                }, settings.testModeMessage)
                : null;

            useEffect(() => {
                if (paymentMethod.id != activePaymentMethod) return;

                const komojuField = document.querySelector(`komoju-fields[payment-type='${paymentMethod.paymentType}']`);
                if (komojuFieldEnabledMethods.includes(paymentMethod.id) && komojuField) komojuField.style.display = 'block';
                const descriptionElement = document.getElementById(`${name}_description`);
                if (descriptionElement) descriptionElement.style.display = 'block';

                const unsubscribe = onPaymentSetup(async () => {
                    if (paymentMethod.id != activePaymentMethod) return;
                    if (!komojuFieldEnabledMethods.includes(paymentMethod.id)) return;
                    if (!settings.inlineFields) return;

                    if (!(komojuField || typeof komojuField.submit === 'function')) {
                        return {
                            type: emitResponse.responseTypes.ERROR,
                            message: 'There was an error',
                        };
                    }

                    function submitFields(fields) {
                        return new Promise((resolve, reject) => {
                            fields.addEventListener("komoju-invalid", reject);
                            fields.submit().then(token => {
                                fields.removeEventListener("komoju-invalid", reject);
                                if (token) {
                                    resolve(token);
                                } else {
                                    reject({
                                        detail: {
                                            errors: [
                                                { message: "Token not found" }
                                            ]
                                        }
                                    });
                                }
                            }).catch(error => {
                                fields.removeEventListener("komoju-invalid", reject);
                                reject(error);
                            });
                        });
                    }

                    try {
                        const token = await submitFields(komojuField);
                        return {
                            type: emitResponse.responseTypes.SUCCESS,
                            meta: {
                                paymentMethodData: {
                                    komoju_payment_token: token.id
                                },
                            },
                        };
                    } catch (e) {
                        const errorMessage = e.detail && e.detail.errors && e.detail.errors.length > 0
                            ? e.detail.errors[0].message
                            : "Unknown error occurred";
                        return {
                            type: emitResponse.responseTypes.ERROR,
                            message: errorMessage
                        };
                    }
                });

                return () => {
                    if (komojuField) komojuField.style.display = 'none';
                    if (descriptionElement) descriptionElement.style.display = 'none';
                    unsubscribe();
                };
            }, [
                activePaymentMethod,
                emitResponse.responseTypes.ERROR,
                emitResponse.responseTypes.SUCCESS
            ]);

            const komojuFields = 
                settings.inlineFields
                    ? createElement('komoju-fields', {
                    'token': '',
                    'name': 'komoju_payment_token',
                    'komoju-api': settings.komojuApi,
                    'publishable-key': settings.publishableKey,
                    'session': settings.session,
                    'payment-type': settings.paymentType,
                    'locale': settings.locale,
                    style: { display: 'none' },
                })
                : null;

            return createElement('div', null, testModeNotice, komojuFields);
        };

        const Block_Gateway = {
            name: name,
            label: label,
            content: createElement(KomojuComponent, null),
            edit: createElement(KomojuComponent, null),
            canMakePayment: () => true,
            ariaLabel: settings.title || 'Payment Method',
            supports: {
                features: settings.supports || ['products'],
            },
        };
        window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway);
    }

    return {
        init: () => {
            const paymentMethodData = window.wc.wcSettings.getSetting('paymentMethodData', {});
            Object.values(paymentMethodData).forEach(registerPaymentMethod);
        }
    };
})();

KomojuPaymentModule.init();
