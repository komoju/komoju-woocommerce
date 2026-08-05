/// <reference types="cypress" />

/**
 * KOMOJU expiry must cancel the WooCommerce order.
 *
 * Regression guard for 3.2.6 (PR #166), where these handlers became no-ops.
 * Unit coverage lives in tests/php/ipn-cancellation-test.php; this spec adds
 * HMAC handling, order lookup and real update_status() side effects.
 *
 * Cancellation only fires when the webhook matches the order's current payment
 * attempt, so these tests run a real konbini checkout to get a genuine
 * komoju_session_id.
 */
describe("KOMOJU for WooCommerce: Expired webhook", () => {
  const webhookHeaders = {
    "X-Komoju-ID": "dummy",
    "X-Komoju-Signature": "dummy",
    "X-Komoju-Event": "payment.expired",
    "User-Agent": "Komoju-Webhook",
    "Content-Type": "application/json",
  };

  // `session` ties the event to the order's current attempt;
  // `external_order_num` is how the handler finds the order.
  const expiredPayload = (orderId, sessionId, overrides = {}) => ({
    id: "evt_expired_test",
    type: "payment.expired",
    resource: "event",
    data: {
      id: "pay_expired_test_123",
      resource: "payment",
      status: "expired",
      amount: 1200,
      tax: 0,
      customer: null,
      payment_deadline: "2023-02-19T14:59:59Z",
      payment_details: {
        type: "konbini",
        email: "dummy@dummy.com",
        store: "family-mart",
      },
      payment_method_fee: 0,
      total: 1200,
      currency: "JPY",
      description: null,
      captured_at: null,
      external_order_num: `WC-${orderId}-A49R0D`,
      metadata: { woocommerce_order_id: orderId },
      created_at: "2023-02-17T11:57:01Z",
      amount_refunded: 0,
      locale: "ja",
      session: sessionId,
      customer_family_name: null,
      customer_given_name: null,
      mcc: null,
      statement_descriptor: null,
      refunds: [],
      refund_requests: [],
      ...overrides,
    },
    created_at: "2023-08-28T02:13:48Z",
    reason: null,
  });

  const postWebhook = (body, event = "payment.expired") =>
    cy.request({
      method: "POST",
      url: "http://localhost:8000/?wc-api=WC_Gateway_Komoju",
      headers: { ...webhookHeaders, "X-Komoju-Event": event },
      body,
    });

  /**
   * Place a real konbini order and yield { orderId, sessionId }.
   *
   * KOMOJU redirects to /sessions/<id>, which is the id stored on the order
   * as komoju_session_id.
   */
  const placeKonbiniOrder = () => {
    cy.setupKomoju(['konbini']);
    cy.setCurrency('JPY');
    cy.clickPaymentTab();
    cy.enablePaymentGateway('komoju_konbini');
    cy.goToStore();
    cy.addItemAndProceedToCheckout();
    cy.fillInAddress();

    cy.get('label[for="radio-control-wc-payment-method-options-komoju_konbini"]').click();
    cy.get('komoju-fields[payment-type="konbini"] iframe').iframe().find('#kb-name').should('exist');
    cy.get('komoju-fields[payment-type="konbini"] iframe').iframe().find('#kb-name').type('Test Test');
    cy.get('komoju-fields[payment-type="konbini"] iframe').iframe().find('#kb-email').type('test@example.com');
    cy.get('komoju-fields[payment-type="konbini"] iframe').iframe().find('[value="family-mart"]').click();

    cy.contains('button', 'Place Order').click({ force: true });
    cy.get('.blockUI,.blockOverlay').should('not.exist');

    // Reaching KOMOJU can be slow.
    cy.contains('How to make a payment at Family Mart', { matchCase: false, timeout: 20000 })
      .should('be.visible');

    return cy.location('pathname').then((pathname) => {
      const sessionId = pathname.split('/sessions/')[1].split('/')[0];
      expect(sessionId, 'session id from KOMOJU redirect').to.be.a('string').and.not.be.empty;

      cy.contains('Return to').click();
      cy.contains('Thank you. Your order has been received.').should('be.visible');

      // The test site uses plain permalinks, so the order id is in the query
      // string rather than the path. Both forms are matched.
      return cy.url().then((url) => {
        const match = url.match(/order-received[=/](\d+)/);
        expect(match, `order id in ${url}`).to.not.be.null;

        return { orderId: match[1], sessionId };
      });
    });
  };

  beforeEach(() => {
    cy.installWordpress();
    cy.signinToWordpress().then(() => {
      cy.installWooCommerce();
      cy.installKomoju();
    });
  });

  it("cancels a pending order when its payment expires", () => {
    placeKonbiniOrder().then(({ orderId, sessionId }) => {
      postWebhook(expiredPayload(orderId, sessionId));

      cy.visit(`/wp-admin/post.php?post=${orderId}&action=edit`);
      cy.get('#order_status').should('have.value', 'wc-cancelled');
      cy.get('#woocommerce-order-notes').should('include.text', 'Payment expired via IPN.');
    });
  });

  it("cancels a pending order when the payment is cancelled on KOMOJU", () => {
    placeKonbiniOrder().then(({ orderId, sessionId }) => {
      const body = expiredPayload(orderId, sessionId, { status: 'cancelled' });
      body.type = 'payment.cancelled';

      postWebhook(body, 'payment.cancelled');

      cy.visit(`/wp-admin/post.php?post=${orderId}&action=edit`);
      cy.get('#order_status').should('have.value', 'wc-cancelled');
      cy.get('#woocommerce-order-notes').should('include.text', 'Payment cancelled via IPN.');
    });
  });

  it("ignores an expiry from a superseded payment attempt", () => {
    // The retry flow PR #166 fixed: an abandoned earlier attempt must not
    // cancel an order whose customer has since started a new payment.
    placeKonbiniOrder().then(({ orderId, sessionId }) => {
      postWebhook(expiredPayload(orderId, `${sessionId}_stale`));

      cy.visit(`/wp-admin/post.php?post=${orderId}&action=edit`);
      cy.get('#order_status').should('not.have.value', 'wc-cancelled');
      cy.get('#woocommerce-order-notes').should('not.include.text', 'Payment expired via IPN.');
    });
  });

});
