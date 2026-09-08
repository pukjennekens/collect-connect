export const statusLabel = (status) => ({
    pending_payment: 'Wacht op betaling', pending: 'In afwachting', paid: 'Betaald',
    cancelled: 'Geannuleerd', expired: 'Verlopen', failed: 'Mislukt',
    processing: 'In behandeling', shipped: 'Verzonden', delivered: 'Bezorgd',
    unfulfilled: 'Nog niet verzonden', awaiting_shipment: 'Wacht op verzending',
    ready: 'Klaar voor verzending', refunded: 'Terugbetaald',
}[status] ?? 'In behandeling');
