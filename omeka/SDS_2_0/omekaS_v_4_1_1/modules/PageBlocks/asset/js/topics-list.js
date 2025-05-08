$(document).ready(function () {
    const sidebar = $('#topic-sidebar');
    var selectingElement;
    
    $('#content').on('click', '.topic-form-add, .topic-form-edit', function () {
        selectingElement = $(this);
        
        if (!selectingElement.hasClass('topic-form-add')) {
            sidebar.find('input').each(function (index, elem) {
                elem = $(elem);
                
                const attachment = selectingElement.parents('.attachment');
                elem.val(
                    attachment.find('input[data-sidebar-id="' + elem.attr('data-sidebar-id') + '"]').val());
            });
        } else {
            sidebar.find('input').val('');
        }
        
        sidebar.find('.query-form-advanced-edit-apply').click();
        Omeka.openSidebar(sidebar);
    });
    
    $('#content').on('click', '#topic-sidebar .confirm-panel > button', function () {
        Omeka.closeSidebar(sidebar);
        if (selectingElement.hasClass('topic-form-add')) {
            const attachments = selectingElement.parents('.attachments');
            const newAttachment = $(attachments.data('template'));
            attachments.append(newAttachment);
            selectingElement = newAttachment.find('.topic-form-edit');
        }
        
        $(this).parents('.sidebar').find('input').each(function (index, elem) {
            elem = $(elem);
            
            const attachment = selectingElement.parents('.attachment');
            attachment.find('input[data-sidebar-id="' + elem.attr('data-sidebar-id') + '"]')
                .val(elem.val());
                
            if (elem.attr("data-sidebar-id") == "topic-data-label") {
                attachment.find('.asset-title').text(elem.val());
            } else if (elem.attr("data-sidebar-id") == "topic-data-icon") {
                const className = (elem.val()) ? "thumbnail fa fa-" + elem.val() :
                    "thumbnail fa fa-question unspecified";
                attachment.find('.thumbnail').attr("class", className);
            }
        });
        
        sidebar.find('.query-form-advanced-edit-apply').click();
    });
});