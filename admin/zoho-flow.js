/**
 * Zoho Flow Connection Page
 * Saves and tests the site-wide Zoho Flow webhook connection.
 */
(function ($) {
  "use strict";

  $(function () {
    const $url = $("#zoho-webhook-url");
    const $save = $("#zoho-save");
    const $test = $("#zoho-test");
    const $spinner = $("#zoho-spinner");
    const $message = $("#zoho-message");
    const $badge = $("#zoho-status-badge");
    const $region = $("#zoho-region");

    if (!$url.length) {
      return;
    }

    function busy(isBusy) {
      $spinner.toggleClass("is-active", isBusy);
      $save.prop("disabled", isBusy);
      $test.prop("disabled", isBusy);
    }

    function showMessage(type, text) {
      $message
        .removeClass("is-success is-error is-info")
        .addClass("is-" + type)
        .html(text)
        .show();
    }

    function setConnected(connected, region) {
      $badge
        .toggleClass("is-connected", connected)
        .toggleClass("is-disconnected", !connected)
        .text(connected ? "Connected" : "Not connected");

      $region.text(connected && region ? "Data centre: " + region : "");
    }

    function request(path, body) {
      return fetch(formBuilderZoho.apiUrl + path, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": formBuilderZoho.nonce,
        },
        body: JSON.stringify(body),
      }).then(function (response) {
        return response.json().then(function (data) {
          if (!response.ok) {
            throw new Error(data.message || "Request failed");
          }
          return data;
        });
      });
    }

    $save.on("click", function () {
      busy(true);
      $message.hide();

      request("zoho-flow/connection", { url: $url.val().trim() })
        .then(function (data) {
          setConnected(data.connected, data.region);
          showMessage(
            "success",
            data.connected
              ? "Connection saved. Send a test to confirm it reaches your flow."
              : "Connection cleared."
          );
        })
        .catch(function (error) {
          setConnected(false, "");
          showMessage("error", error.message);
        })
        .finally(function () {
          busy(false);
        });
    });

    $test.on("click", function () {
      busy(true);
      $message.hide();

      request("zoho-flow/test", { url: $url.val().trim() })
        .then(function (data) {
          const payload = JSON.stringify(data.payload, null, 2);
          showMessage(
            "success",
            "<strong>Test delivered successfully (HTTP " +
              data.status_code +
              ").</strong>" +
              "<p>Open your flow in Zoho Flow and click <em>Test</em> to capture this payload for field mapping.</p>" +
              "<pre>" +
              $("<div>").text(payload).html() +
              "</pre>"
          );
        })
        .catch(function (error) {
          showMessage("error", error.message);
        })
        .finally(function () {
          busy(false);
        });
    });
  });
})(jQuery);
