/// <reference types="cypress" />

describe("KOMOJU for WooCommerce: Refunded webhook", () => {
  beforeEach(() => {
    cy.installWordpress();
    cy.signinToWordpress().then(() => {
      cy.installWooCommerce();
      cy.installKomoju();
    });
  });

  it("sets an order as a refunded based on incoming refunded webhook", () => {
    cy.setCurrency('USD');
    cy.createOrder().then(orderId => {
      cy.request({
        method: "POST",
        url: "http://localhost:8000/?wc-api=WC_Gateway_Komoju",
        headers: {
          "X-Komoju-ID": "dummy",
          "X-Komoju-Signature": "dummy",
          "X-Komoju-Event": "payment.refunded",
          "User-Agent": "Komoju-Webhook",
          "Content-Type": "application/json",
        },
        body: {
          id: "dummy",
          type: "payment.refunded",
          resource: "event",
          data: {
            id: "dummy",
            resource: "payment",
            status: "refunded",
            amount: 1200,
            tax: 0,
            customer: null,
            payment_deadline: "2023-02-19T14:59:59Z",
            payment_details: {
              type: "credit_card",
              email: "dummy@dummy.com",
              brand: "master",
              last_four_digits: "3795",
              month: 9,
              year: 2024,
            },
            payment_method_fee: 0,
            total: 1200,
            currency: "EUR",
            description: null,
            captured_at: "2023-02-17T11:57:05Z",
            external_order_num: `WC-${orderId}-A49R0D`,
            metadata: {
              woocommerce_order_id: String(orderId),
            },
            created_at: "2023-02-17T11:57:01Z",
            amount_refunded: 1200,
            locale: "ja",
            session: "dummy",
            customer_family_name: null,
            customer_given_name: null,
            mcc: null,
            statement_descriptor: null,
            refunds: [
              {
                id: "dummy",
                resource: "refund",
                amount: 1200,
                currency: "EUR",
                payment: "dummy",
                description: "Bulk Chargeback 2023-08-28",
                created_at: "2023-08-28T02:13:46Z",
                chargeback: true,
              },
            ],
            refund_requests: [],
          },
          created_at: "2023-08-28T02:13:48Z",
          reason: null,
        },
      });
      cy.reload();
      cy.get('#woocommerce-order-notes').should('include.text', 'Payment refunded via IPN.');
      cy.get('#woocommerce-order-notes').should('include.text', 'Order status set to refunded.');
    });
  });
});
