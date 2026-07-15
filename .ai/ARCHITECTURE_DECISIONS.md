# Architecture Decisions

## ADR-0011 – Configureerbare decimale precisie

**Status:** Accepted

### Besluit

- Administraties ondersteunen een configureerbare precisie van 0 tot en met 8 decimalen.
- De productstandaard is 2 decimalen en wordt expliciet door de aanroeper aangeleverd.
- Aanvullend beleid voor wijziging van de precisie nadat financiële transacties bestaan, volgt later.

### Consequenties

- Decimale precisie is een afzonderlijk immutable value object.
- Precisie bepaalt niet het afrondingsbeleid; afronding wordt afzonderlijk ontworpen.
