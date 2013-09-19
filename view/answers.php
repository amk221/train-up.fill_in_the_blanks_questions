<form class="tu-answers" action="" method="POST">
  <div class="tu-fill-in-the-blanks">

    <div class="tu-form-row">
      <div class="tu-form-label"></div>
      <div class="tu-form-inputs">
        <?php echo $content; ?>
      </div>
    </div>

    <div class="tu-form-row">
      <div class="tu-form-label"></div>
      <div class="tu-form-inputs">
        <div class="tu-form-input tu-form-button">
          <button type="submit">
            <?php echo apply_filters('tu_form_button', __('Save my answer', 'trainup'), 'save_answer'); ?>
          </button>
        </div>
      </div>
    </div>

    <?php echo $pagination; ?>
    
  </div>
</form>