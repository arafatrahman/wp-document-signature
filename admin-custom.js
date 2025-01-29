jQuery(document).ready(function ($) {
    let signerIndex = 1;

    // Function to add a new signer row
    $('#add-signer-btn').off('click').on('click', function () {
        // This ensures only one click event is bound to the button
        const signerHTML = `
            <div class="row signer-row" style="margin-top:20px;">
                <div class="col-md-5">
                    <input type="text" class="form-control" name="signers[${signerIndex}][name]" placeholder="Name" required>
                    <small class="error-message text-danger d-none"></small>
                </div>
                <div class="col-md-6">
                    <input type="email" class="form-control" name="signers[${signerIndex}][email]" placeholder="Email" required>
                    <small class="error-message text-danger d-none"></small>
                </div>
                <div class="col-md-1" style="margin-left:-20px;">
                    <button type="button" class="btn btn-danger remove-signer">✖</button>
                </div>
            </div>`;

        // Append the signer HTML to the container
        $('#signers-container').append(signerHTML);

        // Increment the signer index for the next signer
        signerIndex++;
    });

    // Remove a signer row when clicking the remove button
    $(document).on('click', '.remove-signer', function () {
        $(this).closest('.signer-row').remove();
    });

    // Open the modal and set the post ID when clicking "Send Documents" button
    $('.send-documents-button').on('click', function () {
        const postId = $(this).data('post-id');
        $('#modal-post-id').val(postId);
    });

    // Handle form submission
    $('#send-documents-submit').off('click').on('click', function () {
        let isValid = true;

        // Clear previous error messages and styles
        $('.form-control').removeClass('is-invalid');
        $('.error-message').addClass('d-none').text('');

        // Validate Name and Email fields
        $('#send-documents-form input[name^="signers"]').each(function () {
            const nameInput = $(this).closest('.row').find('input[name*="name"]');
            const emailInput = $(this).closest('.row').find('input[name*="email"]');

            const name = nameInput.val();
            const email = emailInput.val();

            let hasError = false;

            // Name Validation: Ensure it's two words
            if (name.trim().split(/\s+/).length < 2) {
                nameInput.addClass('is-invalid');
                nameInput.closest('.col-md-5').find('.error-message').text('Name must contain at least two words.').removeClass('d-none');
                hasError = true;
            }

            // Email Validation: Ensure it's a valid email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                emailInput.addClass('is-invalid');
                emailInput.closest('.col-md-5').find('.error-message').text('Please provide a valid email address.').removeClass('d-none');
                hasError = true;
            }

            // If there's an error, set isValid to false
            if (hasError) {
                isValid = false;
            }
        });

        // If validation fails, do not submit the form
        if (!isValid) {
            return; // Prevent form submission if validation fails
        }

        // Serialize the form data, including the nonce field
        let formData = $('#send-documents-form').serialize(); // Form data is serialized here
        console.log('Serialized Form Data:', formData); // Log serialized data to console

        // Send the data via AJAX
        $.post(ajax_obj.ajaxurl, {
            action: 'send_documents',  // The action to trigger on the server
            data: formData         // Send the serialized data here
        }, function (response) {
            if (response.success) {
                alert('Documents sent successfully!');
                location.reload(); // Reload page on success
            } else {
                alert(response.data.message); // Error message
            }
        });
    });
});
