(function ($) {
  function setStatus($box, msg, type) {
    $box
      .removeClass("notice notice-error notice-success notice-warning")
      .addClass("notice")
      .addClass(type === "success" ? "notice-success" : "notice-error")
      .text(msg)
      .show();
  }

  $(document).on("click", ".tppl-translate-btn", function () {
    if (typeof TPPL_ADDON === "undefined") {
      return;
    }

    var $btn = $(this);
    var postId = parseInt($btn.data("post-id"), 10);
    var target = String($("#tppl_target_lang").val() || "");
    var $status = $btn.closest("#tppl_translateplus_metabox").find(".tppl-addon__status");

    if (!target) {
      setStatus($status, TPPL_ADDON.i18n.selectLang, "error");
      return;
    }

    $btn.prop("disabled", true).text(TPPL_ADDON.i18n.working);
    $status.hide();

    $.post(TPPL_ADDON.ajaxUrl, {
      action: TPPL_ADDON.action,
      nonce: TPPL_ADDON.nonce,
      postId: postId,
      target: target,
    })
      .done(function (resp) {
        if (resp && resp.success) {
          setStatus($status, resp.data && resp.data.message ? resp.data.message : "OK", "success");
          if (resp.data && resp.data.editUrl) {
            window.location.href = resp.data.editUrl;
          }
          return;
        }

        var msg = (resp && resp.data && resp.data.message) || TPPL_ADDON.i18n.error;
        setStatus($status, msg, "error");
      })
      .fail(function () {
        setStatus($status, TPPL_ADDON.i18n.error, "error");
      })
      .always(function () {
        $btn.prop("disabled", false).text("Translate with TranslatePlus");
      });
  });
})(jQuery);

