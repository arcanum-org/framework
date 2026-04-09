# Arcanum Htmx

First-class htmx support for the Arcanum framework, targeting htmx 4.

**The zero-ceremony default:** developers write normal HTML with `id` attributes. When an htmx request arrives with `HX-Target: sidebar`, the framework finds `<div id="sidebar">` in the template source, extracts just that element, compiles and renders only the extracted slice, and adapts the response shape for the swap mode (outerHTML includes the element, innerHTML strips the wrapper). Handlers stay transport-agnostic — they return data, the framework figures out the shape.

## Package contents

- `HtmxRequest` — read-side decorator over `ServerRequestInterface` with typed accessors for every htmx request header.
- `HtmxRequestType` — enum: `Full` | `Partial`.
- `ClientBroadcast` — marker interface on domain events that project as `HX-Trigger` headers. Sub-interfaces `BroadcastAfterSwap` and `BroadcastAfterSettle` for timing control.

Full documentation will follow as the package is built out.
