<!DOCTYPE html>
<html>
<head>
  <script src="https://artifacts.flywire.com/sdk/js/v0/sandbox.main.js"></script>
</head>
<body>

<div id="my-container-id"></div>

<script type="module">
(async () => {

  const sdk = await window.FlywireSDK(
    "fk_VlhaNHRZdnpqRkowOFh2TDloc1pQZz09"
  );

  const elements = await sdk.elements();

  const checkout = await elements.create("payment", {
    sessionId: "63b7efc0-5daa-4edf-a4c8-0e99244716bc",
    displayMode: "container"
  });

  checkout.onEvent("success", (event) => {
    console.log("Checkout success", event);
    // 👉 Call backend to confirm session
  });

  checkout.onEvent("error", (event) => {
    console.error("Checkout error", event);
  });

  checkout.mount("my-container-id");

})();
</script>

</body>
</html>
