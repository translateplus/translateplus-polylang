(function ($) {
  function escapeHtml(text) {
    return $("<div/>").text(text).html();
  }

  function setBusy(isBusy) {
    var $btn = $("#tppl-refresh-status");
    var $spinner = $("#tppl-refresh-spinner");
    if (!$btn.length) {
      return;
    }

    $btn.prop("disabled", isBusy);
    $btn.text(isBusy ? TPPL_SETTINGS.i18n.refreshing : TPPL_SETTINGS.i18n.refresh);
    if ($spinner.length) {
      $spinner.toggleClass("is-active", isBusy);
    }
  }

  function setFeedback(message) {
    var $feedback = $("#tppl-refresh-feedback");
    if (!$feedback.length) {
      return;
    }
    if (!message) {
      $feedback.hide().text("");
      return;
    }
    $feedback.text(message).show();
  }

  function showPageNotice(type, message) {
    var $wrap = $("#tppl-page-notices");
    if (!$wrap.length) {
      return;
    }
    var cls = type === "success" ? "notice-success" : "notice-error";
    $wrap.html(
      '<div class="notice ' +
        cls +
        ' is-dismissible"><p>' +
        escapeHtml(message) +
        "</p></div>"
    );
  }

  function clearPageNotice() {
    var $wrap = $("#tppl-page-notices");
    if ($wrap.length) {
      $wrap.empty();
    }
  }

  function setSaveBusy(isBusy) {
    var $btn = $("#tppl-save-api-submit");
    if (!$btn.length) {
      return;
    }
    $btn.prop("disabled", isBusy);
    $btn.val(isBusy ? TPPL_SETTINGS.i18n.saving : TPPL_SETTINGS.i18n.save);
  }

  $(document).on("click", "#tppl-refresh-status", function () {
    if (typeof TPPL_SETTINGS === "undefined") {
      return;
    }

    setFeedback("");
    setBusy(true);

    $.post(TPPL_SETTINGS.ajaxUrl, {
      action: TPPL_SETTINGS.action,
      nonce: TPPL_SETTINGS.nonce,
    })
      .done(function (resp) {
        if (resp && resp.success && resp.data && typeof resp.data.html === "string") {
          $("#tppl-account-summary").html(resp.data.html);
          return;
        }

        var msg = (resp && resp.data && resp.data.message) || TPPL_SETTINGS.i18n.error;
        setFeedback(msg);
      })
      .fail(function () {
        setFeedback(TPPL_SETTINGS.i18n.error);
      })
      .always(function () {
        setBusy(false);
      });
  });

  $(document).on("submit", "#tppl-save-api-form", function (e) {
    e.preventDefault();

    if (typeof TPPL_SETTINGS === "undefined") {
      return;
    }

    var $input = $("#tppl-api-key-input");
    var apiKey = ($input.val() || "").trim();

    clearPageNotice();
    setSaveBusy(true);

    $.post(TPPL_SETTINGS.ajaxUrl, {
      action: TPPL_SETTINGS.saveAction,
      nonce: TPPL_SETTINGS.saveNonce,
      api_key: apiKey,
    })
      .done(function (resp) {
        if (resp && resp.success && resp.data) {
          if (typeof resp.data.message === "string" && resp.data.message) {
            showPageNotice("success", resp.data.message);
          }
          if (typeof resp.data.connectionHtml === "string") {
            $("#tppl-connection-card").html(resp.data.connectionHtml);
          }
          if (typeof resp.data.accountHtml === "string") {
            $("#tppl-account-status-card").html(resp.data.accountHtml);
          }
          return;
        }

        var errMsg =
          (resp && resp.data && resp.data.message) || TPPL_SETTINGS.i18n.saveError;
        showPageNotice("error", errMsg);
      })
      .fail(function () {
        showPageNotice("error", TPPL_SETTINGS.i18n.saveError);
      })
      .always(function () {
        setSaveBusy(false);
      });
  });
})(jQuery);
