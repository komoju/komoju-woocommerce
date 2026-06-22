/// <reference types="cypress" />

describe('KOMOJU for WooCommerce: Admin', () => {
  before(() => {
    cy.installWordpress();
    cy.signinToWordpress().then(() => {
      cy.installWooCommerce();
      cy.installKomoju();
    });
  });

  beforeEach(() => {
    cy.signinToWordpress()
  })

  it('lets me add and remove specialized payment gateways', () => {
    cy.setupKomoju(['konbini', 'credit_card']);
    cy.clickPaymentTab();

    cy.contains('KOMOJU - Konbini');
    cy.contains('KOMOJU - Credit Card');

    cy.setupKomoju(['paypay']);
    cy.clickPaymentTab();

    cy.contains('KOMOJU - PayPay');
  })

  it('LINE pay should not exist', () => {
    cy.setupKomoju(['konbini', 'credit_card']);
    cy.clickPaymentTab();

    cy.contains('KOMOJU - Konbini');
    cy.contains('KOMOJU - Credit Card');

    cy.setupKomoju(['paypay', 'linepay']);
    cy.clickPaymentTab();

    cy.contains('KOMOJU - PayPay')
    cy.contains('KOMOJU - LINE Pay').should('not.exist');
  });

  it('lets me change the KOMOJU endpoint', () => {
    cy.visit('/wp-admin/admin.php?page=wc-settings&tab=komoju_settings&section=api_settings');

    // The "Edit" button only shows while the endpoint is still the default. If a
    // previous run (or retry) already changed it, the input is enabled directly.
    cy.get('.komoju-endpoint-komoju_woocommerce_api_endpoint').then($td => {
      const $edit = $td.find('.komoju-endpoint-edit');
      if ($edit.length > 0) { cy.wrap($edit).click(); }
    });
    cy.get('#komoju_woocommerce_api_endpoint').should('not.be.disabled').clear().type('https://example.com');
    cy.contains('Save changes').should('not.be.disabled').click();

    cy.contains('Payment methods').click();
    cy.get('#mainform').should('include.text', 'Failed to connect to KOMOJU. Please ensure the correct secret key is set by reconnecting via the "Reconnect with KOMOJU" button above.');
    cy.contains('API settings').click();

    cy.get('#komoju_woocommerce_api_endpoint').should('not.be.disabled').clear().type('https://komoju.com');
    cy.contains('Save changes').should('not.be.disabled').click();

    cy.contains('Payment methods').click();
    cy.get('#mainform').should('not.include.text', 'Failed to connect to KOMOJU. Please ensure the correct secret key is set by reconnecting via the "Reconnect with KOMOJU" button above.');
  })

  it('updates secret key with one-click setup', () => {
    // The credential fields are write-only and a blank form submission keeps the
    // existing key, so the keys cannot be cleared through the settings form.
    // Reset to a disconnected state deterministically via the quick-setup
    // endpoint, which writes the options directly. A fresh nonce is generated
    // every time the settings page renders the connect button.
    cy.visit('/wp-admin/admin.php?page=wc-settings&tab=komoju_settings');
    cy.get('a.komoju-setup').invoke('attr', 'href').then((href) => {
      const nonce = new URL(href, 'http://localhost:8000').searchParams.get('nonce');
      cy.request({
        method: 'POST',
        url: '/?wc-api=WC_Gateway_Komoju',
        form: true,
        body: { secret_key: '', publishable_key: '', webhook_secret: '', merchant_name: '', nonce },
      }).its('status').should('eq', 200);
    });

    // With no secret key saved, the payment methods section shows the
    // "not connected" prompt and the connect button reads "Sign into KOMOJU".
    cy.visit('/wp-admin/admin.php?page=wc-settings&tab=komoju_settings');
    cy.contains('Payment methods').click();
    cy.get('#mainform').should('include.text', 'Once signed into KOMOJU, you can select payment methods to use as WooCommerce gateways.');
    cy.get('.komoju-setup').should('include.text', 'Sign into KOMOJU');

    // Grab a fresh nonce from the connect button.
    let nonce;
    cy.get('a.komoju-setup').invoke('attr', 'href').then((href) => {
      nonce = new URL(href, 'http://localhost:8000').searchParams.get('nonce');
    });

    const options = {
      method: 'POST',
      url: '/?wc-api=WC_Gateway_Komoju',
      body: {
        secret_key: 'abc123',
        nonce: 'wrong',
        webhook_secret: 'webhooks123'
      },
      failOnStatusCode: false,
      form: true
    }

    cy.request(options)
      .should(response => {
        expect(response.status).to.eq(422)
        expect(response.body).to.include('Invalid nonce. Please try again.')
      })
      .then(() => {
        options.body.nonce = nonce;
        options.failOnStatusCode = true;

        cy.request(options)
          .should(response => {
            expect(response.status).to.eq(200)
          })
      })

    cy.reload()
    cy.get('.komoju-setup').should('include.text', 'Reconnect with KOMOJU')
    cy.contains('API settings').click()
    // The key fields are write-only: the stored value is never rendered back
    // into the input. Instead, a saved key is indicated by a masked placeholder
    // (dots, or the key prefix for sk_/pk_ keys). Assert the field is empty but
    // shows the masked "saved" indicator.
    cy.get('#komoju_woocommerce_secret_key')
      .should('have.value', '')
      .and('have.attr', 'placeholder', '••••••••••••••••')
    cy.get('#komoju_woocommerce_webhook_secret')
      .should('have.value', '')
      .and('have.attr', 'placeholder', '••••••••••••••••')
  })
});
