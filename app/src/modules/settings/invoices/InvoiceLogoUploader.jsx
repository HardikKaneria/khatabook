import { DeleteOutlined, InboxOutlined, LoadingOutlined } from "@ant-design/icons";
import { Button, Upload } from "antd";

import { removeInvoiceLogo, uploadInvoiceLogo } from "./invoiceSettingsApi";

const MAX_LOGO_BYTES = 2 * 1024 * 1024;
const ALLOWED_LOGO_TYPES = ["image/jpeg", "image/png", "image/webp"];

export default function InvoiceLogoUploader({
    logoUrl,
    disabled = false,
    onSettingsChange,
    onError,
    onBusyChange,
}) {
    const handleBeforeUpload = (file) => {
        if (!ALLOWED_LOGO_TYPES.includes(file.type)) {
            onError?.("Upload a PNG, JPG, or WEBP logo.");
            return Upload.LIST_IGNORE;
        }

        if ((file.size || 0) > MAX_LOGO_BYTES) {
            onError?.("Logo must be 2 MB or smaller.");
            return Upload.LIST_IGNORE;
        }

        return true;
    };

    const handleUpload = async ({ file, onSuccess, onError: onUploadError }) => {
        onBusyChange?.(true);
        try {
            const response = await uploadInvoiceLogo(file);
            onSettingsChange?.({
                logo_url: response?.settings?.logo_url || response?.logo_url || "",
            });
            onSuccess?.(response, file);
        } catch (error) {
            const message = error?.message || "Failed to upload logo.";
            onError?.(message);
            onUploadError?.(error);
        } finally {
            onBusyChange?.(false);
        }
    };

    const handleRemove = async () => {
        onBusyChange?.(true);
        try {
            const response = await removeInvoiceLogo();
            onSettingsChange?.({
                logo_url: response?.settings?.logo_url || response?.logo_url || "",
            });
        } catch (error) {
            onError?.(error?.message || "Failed to remove logo.");
        } finally {
            onBusyChange?.(false);
        }
    };

    return (
        <div className="invoice-logo-uploader">
            <Upload.Dragger
                accept=".jpg,.jpeg,.png,.webp"
                disabled={disabled}
                maxCount={1}
                multiple={false}
                showUploadList={false}
                beforeUpload={handleBeforeUpload}
                customRequest={handleUpload}
            >
                <p className="ant-upload-drag-icon">
                    {disabled ? <LoadingOutlined /> : <InboxOutlined />}
                </p>
                <p className="invoice-logo-uploader__title">
                    Drag your company logo here, or click to upload
                </p>
                <p className="invoice-logo-uploader__hint">
                    PNG, JPG, or WEBP up to 2 MB. The uploaded logo is used in preview, PDF, and emailed invoices.
                </p>
            </Upload.Dragger>

            <div className="invoice-logo-preview-card">
                <div className="invoice-logo-preview-card__media">
                    {logoUrl ? (
                        <img src={logoUrl} alt="Current invoice logo" className="invoice-logo-preview-card__image" />
                    ) : (
                        <div className="invoice-logo-preview-card__empty">No logo uploaded</div>
                    )}
                </div>
                <div className="invoice-logo-preview-card__content">
                    <h4>Current Invoice Logo</h4>
                    <p>
                        {logoUrl
                            ? "Uploading a new file replaces the current logo immediately."
                            : "Upload a square or wide logo for the most stable invoice layout."}
                    </p>
                    <div className="invoice-logo-preview-card__actions">
                        <Button
                            type="default"
                            icon={<DeleteOutlined />}
                            onClick={handleRemove}
                            disabled={disabled || !logoUrl}
                        >
                            Remove Logo
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    );
}
