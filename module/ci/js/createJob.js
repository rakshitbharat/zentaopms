$(function()
{
    repoTypeChanged('Git');
    triggerTypeChanged(triggerType);
});

function repoTypeChanged(type) {
    if(type.indexOf('Subversion') > -1) {
        $('.svn-fields').removeClass('hidden');
    } else {
        $('.svn-fields').addClass('hidden');
    }
}

function triggerTypeChanged(type) {
    if(type == 'tag') {
        $('.tag-fields').removeClass('hidden');
        $('.comment-fields').addClass('hidden');

        scheduleTypeChanged();
    } else if(type == 'commit') {
        $('.tag-fields').addClass('hidden');
        $('.comment-fields').removeClass('hidden');

        $('.custom-fields').addClass('hidden');

        scheduleTypeChanged();
    } else if(type == 'schedule') {
        $('.tag-fields').addClass('hidden');
        $('.comment-fields').addClass('hidden');

        var val = $("input[name='scheduleType']:checked").val();
        scheduleTypeChanged(val? val: 'custom');
    }
}

function scheduleTypeChanged(type) {
    if(type == 'custom') {
        $('.schedule-fields').removeClass('hidden');

        $('.custom-fields').removeClass('hidden');
    } else {
        $('.schedule-fields').addClass('hidden');

        $('.custom-fields').addClass('hidden');
    }
}