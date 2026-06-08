/// <reference types="cypress" />

describe("KOMOJU for WooCommerce: Webhook replay protection", () => {
  beforeEach(() => {
    cy.installWordpress();
    cy.signinToWordpress().then(() => {
      cy.installWooCommerce();
      cy.installKomoju();
    });
  });

  it("processes a webhook event only once and ignores duplicates", () => {
    cy.setCurrency('USD');
    cy.createOrder().then(orderId => {
      const webhookBody = {
        id: "evt_replay_test",
        type: "payment.refunded",
        resource: "event",
        data: {
          id: "pay_replay_test_123",
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
          currency: "USD",
          description: null,
          captured_at: "2023-02-17T11:57:05Z",
          external_order_num: `WC-${orderId}-A49R0D`,
          metadata: {
            woocommerce_order_id: orderId,
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
              id: "refund_1",
              resource: "refund",
              amount: 1200,
              currency: "USD",
              payment: "pay_replay_test_123",
              description: "Test refund",
              created_at: "2023-08-28T02:13:46Z",
              chargeback: false,
            },
          ],
          refund_requests: [],
        },
        created_at: "2023-08-28T02:13:48Z",
        reason: null,
      };

      const webhookHeaders = {
        "X-Komoju-ID": "dummy",
        "X-Komoju-Signature": "dummy",
        "X-Komoju-Event": "payment.refunded",
        "User-Agent": "Komoju-Webhook",
        "Content-Type": "application/json",
      };

      // Send the webhook the first time — should process
      cy.request({
        method: "POST",
        url: "http://localhost:8000/?wc-api=WC_Gateway_Komoju",
        headers: webhookHeaders,
        body: webhookBody,
      });

      // Verify the order was refunded
      cy.visit(`/wp-admin/post.php?post=${orderId}&action=edit`);
      cy.get('#woocommerce-order-notes').should('include.text', 'Payment refunded via IPN.');
      cy.get('#woocommerce-order-notes').should('include.text', 'Order status set to refunded.');

      // Count the number of order notes
      cy.get('#woocommerce-order-notes .note_content').then($notes => {
        const noteCountAfterFirst = $notes.length;

        // Send the exact same webhook again — should be ignored (replay)
        cy.request({
          method: "POST",
          url: "http://localhost:8000/?wc-api=WC_Gateway_Komoju",
          headers: webhookHeaders,
          body: webhookBody,
        });

        // Reload and verify no new notes were added
        cy.reload();
        cy.get('#woocommerce-order-notes .note_content').should('have.length', noteCountAfterFirst);
      });
    });
  });
});
