$(function()
{
    $('#type').change(function()
    {
        var type = $(this).val();
        $('#sendTypeTR').toggle(type != 'dingding' && type != 'dingapi');
        $('#secretTR').toggle(type == 'dingding');
        $('#urlTR').toggle(type != 'dingapi' && type != 'wechatApi');
        $('.dingapiTR').toggle(type == 'dingapi');
        $('.wechatTR').toggle(type == 'wechatApi');
        $('#paramsTR').toggle(type != 'bearychat' && type != 'dingding' && type != 'dingapi' && type != 'wechatApi' && type != 'weixin');
        $('#urlNote').html(urlNote[type]);
    });

    $('.objectType').click(function()
    {
        if($(this).prop('checked'))
        {
            $(this).parent().parent().next().find('input[type=checkbox]').attr('checked', 'checked');
        }
        else
        {
            $(this).parent().parent().next().find('input[type=checkbox]').removeAttr('checked');
        }
    });

    $('#allParams, #allActions').click(function()
    {
        if($(this).prop('checked'))
        {
            $(this).parents('tr').find('input[type=checkbox]').attr('checked', 'checked');
        }
        else
        {
            $(this).parents('tr').find('input[type=checkbox][disabled!=disabled]').removeAttr('checked');
        }
    });

    $('#name').focus();
    $('#type').change();
    $('#paramstext').attr('disabled', 'disabled');
});
