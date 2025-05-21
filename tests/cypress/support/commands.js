/// <reference types="cypress" />

// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************
//
//
// -- This is a parent command --
// Cypress.Commands.add('login', (email, password) => { ... })
//
//
// -- This is a child command --
// Cypress.Commands.add('drag', { prevSubject: 'element'}, (subject, options) => { ... })
//
//
// -- This is a dual command --
// Cypress.Commands.add('dismiss', { prevSubject: 'optional'}, (subject, options) => { ... })
//
//
// -- This will overwrite an existing command --
// Cypress.Commands.overwrite('visit', (originalFn, url, options) => { ... })

Cypress.Commands.add('installWordpress', () => {
  cy.visit('/wp-admin');

  cy.window().then(win => {
    if (!win.document.querySelector('#setup')) {
      cy.log('Wordpress already installed');
      return;
    }
    cy.log('Wordpress not yet installed. Installing now.');

    cy.contains('English (United States)').click();
    cy.get('#language-continue').click();

    cy.get('#weblog_title').clear().type('Degica Mart');
    cy.get('#user_login').clear().type('degica');
    cy.get('#pass1').clear().type('deg1kaX7reme!');
    cy.get('#admin_email').clear().type('dev@degica.com');
    cy.get('#submit').click();
  });
});

Cypress.Commands.add('signinToWordpress', () => {
  cy.visit('/wp-admin');
  cy.wait(1000);

  cy.get('body').then(($body) => {
    if (!$body.find('#loginform').length) {
      cy.log('Already logged in');
      return;
    }
    cy.log('Logging in');
    cy.get('#user_login').should('be.visible').clear().type('degica');
    cy.get('#user_pass').should('be.visible').clear().type('deg1kaX7reme!');
    cy.get('#wp-submit').should('be.visible').click();
  });
  cy.wait(1000);
});

Cypress.Commands.add('installWooCommerce', () => {
  cy.visit('/wp-admin/plugins.php');

  cy.window().then(win => {
    if (!win.document.querySelector('tr[data-slug="woocommerce"].inactive')) {
      cy.log('WooCommerce already activated');
      return;
    }

    cy.get('tr[data-slug="woocommerce"]').contains('Activate').click();
    cy.wait(1000);

    cy.visit('/wp-admin/edit.php?post_type=product&page=product_importer');
    cy.get('#upload').selectFile({
      contents: Cypress.Buffer.from('ID,Type,SKU,Name,Published,"Is featured?","Visibility in catalog","Short description",Description,"Date sale price starts","Date sale price ends","Tax status","Tax class","In stock?",Stock,"Low stock amount","Backorders allowed?","Sold individually?","Weight (kg)","Length (cm)","Width (cm)","Height (cm)","Allow customer reviews?","Purchase note","Sale price","Regular price",Categories,Tags,"Shipping class",Images,"Download limit","Download expiry days",Parent,"Grouped products",Upsells,Cross-sells,"External URL","Button text",Position\n10,simple,komoju-sticker,"KOMOJU Sticker",1,0,visible,,"The best sticker around",,,taxable,,1,,,0,0,,,,,1,,,12,Uncategorized,,,,,,,,,,,,0'
      ),
      fileName: 'example-product.csv',
      mimeType: 'text/csv'
    });
    cy.contains('Continue').click();
    cy.contains('Run the importer').click();
    cy.contains('Import complete! 1 product imported').should('exist');
  });
});

Cypress.Commands.add('installKomoju', () => {
  cy.visit('/wp-admin/plugins.php');

  cy.window().then(win => {
    if (!win.document.querySelector('tr[data-slug="komoju-japanese-payments"].inactive')) {
      cy.log('KOMOJU already activated');
      return;
    }

    cy.get('tr[data-slug="komoju-japanese-payments"]').contains('Activate').click();
  });
});

Cypress.Commands.add('setupKomoju', (
  paymentTypes = [],
  // fake-mart
  secretKey = 'sk_27dbcf7af57ad9088b8c95792c6f24d2398e771c',
  publishableKey = 'pk_c05e982fa446efa4ff740d1f055a45a4e0c21d5f'
) => {
  cy.visit('/wp-admin/admin.php?page=wc-settings&tab=komoju_settings&section=api_settings');

  cy.get('#komoju_woocommerce_secret_key').clear().type(secretKey);
  cy.get('#komoju_woocommerce_publishable_key').clear().type(publishableKey);
  cy.get('.komoju-endpoint-komoju_woocommerce_fields_url').then($element => {
    const $edit = $element.find('.komoju-endpoint-edit');
    if ($edit.length > 0) { return cy.wrap($edit).click(); }
  });
  cy.get('#komoju_woocommerce_fields_url').clear().type('https://multipay-staging.test.komoju.com/fields.js');
  cy.contains('Save changes').click();

  cy.contains('Payment methods').click();
  cy.wait(1000);

  cy.get('input[type="checkbox"]').each($match => {
    $match.each(function () {
      if (this.checked) cy.wrap($match).click();
    });
  });

  paymentTypes.forEach(slug => {
    cy.get('body').then($body => {
      if ($body.find(`input[type="checkbox"][value="${slug}"]`).length) {
        cy.get(`input[type="checkbox"][value="${slug}"]`)
          .click()
          .then(() => {
            cy.log(`Komoju payment method checkbox for "${slug}" found and clicked.`);
          });
      } else {
        cy.log(`Komoju payment method checkbox for "${slug}" not found.`);
      }
    });
  });

  cy.contains('Save changes').click();
});

Cypress.Commands.add('enablePaymentGateway', (slug) => {
  cy.get(`tr[data-gateway_id="${slug}"]`)
    .find('.woocommerce-input-toggle')
    .each($match => {
      if ($match[0].textContent === 'No') cy.wrap($match).click();
    });
});

Cypress.Commands.add('goToStore', () => {
  cy.visit('/?page_id=6');
});

Cypress.Commands.add('fillInAddress', () => {
  cy.get('.wc-block-components-address-address-wrapper').then(($el) => {
    if ($el.hasClass('is-editing')) {
      cy.get('#billing-last_name').clear().type('Johnson');
      cy.get('#billing-first_name').clear().type('Test');
      cy.get('#billing-country').select('Japan');
      cy.get('#billing-state').select('Tokyo');
      cy.get('#billing-postcode').clear().type('180-0004');
      cy.get('#billing-city').clear().type('Musashino');
      cy.get('#billing-address_1').clear().type('address');
      cy.get('#billing-phone').clear().type('123123213213213');
    }
  });
});

Cypress.Commands.add('iframe', { prevSubject: 'element' }, ($iframe) => {
  // get the iframe > document > body
  // and retry until the body element is not empty
  Cypress.log({
    name: 'iframe',
    consoleProps() {
      return {
        iframe: $iframe,
      };
    },
  });

  return cy.wrap($iframe)
    .its('0.contentDocument.body').should('not.be.empty')
    // wraps "body" DOM element to allow
    // chaining more Cypress commands, like ".find(...)"
    // https://on.cypress.io/wrap
    .then(cy.wrap)
})

Cypress.Commands.add('createOrder', () => {
  cy.visit('/wp-admin/post-new.php?post_type=shop_order');
  cy.get('.button.add-line-item').click();
  cy.get('.button.add-order-item').click();
  cy.get('.wc-backbone-modal-main .select2-selection.select2-selection--single').click();
  cy.get('.select2-search--dropdown .select2-search__field').type('sticker');
  cy.get('.select2-results__option--highlighted').click();
  cy.get('#btn-ok').click();
  cy.get('.calculate-action').click();
  cy.get('.save_order').click();
  return cy.get('#post_ID').invoke('val');
});

Cypress.Commands.add('addItemAndProceedToCheckout', () => {
  cy.get('body').then(($body) => {
    if ($body.find('button:contains("Add to cart")').length > 0) {
      cy.contains('button', 'Add to cart').click({ force: true });
    }
  });

  cy.get('.wc-block-mini-cart__button').should('be.visible').click({ force: true });
  cy.contains('Go to checkout').should('be.visible').click();
});

Cypress.Commands.add('clickPaymentTab', () => {
  cy.visit('/wp-admin/admin.php?page=wc-settings&tab=checkout');
});

Cypress.Commands.add('setCurrency', (currency) => {
  cy.visit('/wp-admin/admin.php?page=wc-settings')
  cy.get('#select2-woocommerce_currency-container').type(`${currency}{enter}`);
  cy.get('#select2-woocommerce_currency-container').invoke('val').then((val) => {
    if (val !== currency.toUpperCase()) {
      cy.contains('Save changes').then(($btn) => {
        if (!$btn.is(':disabled')) {
          cy.wrap($btn).click();
        }
      })
    }
  })
});