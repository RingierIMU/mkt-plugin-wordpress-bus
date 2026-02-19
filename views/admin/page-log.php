<div class="wrap">
    <h1><?php echo esc_html($args['admin_page_title']); ?></h1>
    <h3>Use the Error Log below to spot any error during the API calls</h3>
    <form name="log_page_form" action="" method="post">
        <table class="form-table">
            <tr>
                <td>(Only the latest 10 entries will be displayed - pay attention to the DATE!)</td>
            </tr>
            <tr>
                <td><?php echo esc_html($args['error_msg']); ?></td>
            </tr>
            <tr>
                <td width="100%">
                    <textarea name="txtlog" class="large-text code" style="height:450px;" wrap="off" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"><?php echo $args['txtlog_value']; ?></textarea>
                </td>
            </tr>
            <tr>
                <td>
                    <input type="submit" class="button-primary" value="Clear Error Log" name="clearlog_btn" />
                </td>
            </tr>
        </table>
    </form>
</div>
