import type { ReactNode } from 'react'
import { Controller, useFormContext, type FieldValues, type Path } from 'react-hook-form'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import FormHelperText from '@mui/material/FormHelperText'
import Typography from '@mui/material/Typography'
import CloudUploadIcon from '@mui/icons-material/CloudUpload'

interface FormFileFieldProps<T extends FieldValues> {
  name: Path<T>
  label: string
  helperText?: string
}

export function FormFileField<T extends FieldValues>({ name, label, helperText }: FormFileFieldProps<T>): ReactNode {
  const { control } = useFormContext<T>()

  return (
    <Controller
      name={name}
      control={control}
      render={({ field, fieldState }) => {
        const fileValue: unknown = field.value
        const file = fileValue instanceof File ? fileValue : null
        return (
          <Box>
            <input
              id={name}
              type="file"
              accept="image/jpeg,image/png,image/webp"
              style={{ display: 'none' }}
              onChange={(event) => {
                const selected = event.target.files?.[0] ?? null
                field.onChange(selected)
              }}
            />
            <label htmlFor={name}>
              <Button component="span" variant="outlined" startIcon={<CloudUploadIcon />} fullWidth sx={{ py: 1 }} color={fieldState.error ? 'error' : 'primary'}>
                {file ? file.name : label}
              </Button>
            </label>
            <FormHelperText sx={{ mt: 0.5 }}>
              {file ? `${Math.round(file.size / 1024)} KB selected` : 'JPG, PNG or WebP, up to 5 MB'}
            </FormHelperText>
            {fieldState.error ? (
              <Typography variant="caption" color="error">
                {fieldState.error.message}
              </Typography>
            ) : helperText ? (
              <Typography variant="caption" color="text.secondary">
                {helperText}
              </Typography>
            ) : null}
          </Box>
        )
      }}
    />
  )
}