$(document).ready(function () {
    $("body").on("DOMNodeInserted", function() {
        $(".chosen-select.media-details-property:only-child").chosen(chosenOptions);
    });
});